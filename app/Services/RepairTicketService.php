<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CustomerDevice;
use App\Models\Payment;
use App\Models\RepairTicket;
use App\Models\Sequence;
use App\Models\TicketLine;
use App\Models\User;
use App\Models\VerificationToken;
use App\Repositories\Contracts\RepairTicketRepositoryInterface;
use App\Support\Api\ApiException;
use App\Support\Api\ErrorCode;
use App\Support\TicketStateMachine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RepairTicketService
{
    public function __construct(
        private readonly RepairTicketRepositoryInterface $tickets,
        private readonly TicketEventRecorder $events,
    ) {}

    public function list(): LengthAwarePaginator
    {
        return $this->tickets->paginate();
    }

    /**
     * A ticket's own branch_id gates whether it's visible at all (RepairTicket
     * is branch-scoped); its customer or assigned technician may legitimately
     * belong to a different branch (repeat customers move between branches —
     * the same reason the IMEI-history lookup bypasses BranchScope). Without
     * this, eager-loading those relations under a mismatched actor silently
     * nulls them out and crashes the resource layer on the null model.
     */
    public function loadDisplayRelations(RepairTicket $ticket): RepairTicket
    {
        return $ticket->load([
            'customer' => fn ($query) => $query->withoutGlobalScopes(),
            'customerDevice.deviceModel',
            'assignedTechnician' => fn ($query) => $query->withoutGlobalScopes(),
            'verificationToken',
        ]);
    }

    public function create(array $data, User $actor): RepairTicket
    {
        return DB::transaction(function () use ($data, $actor) {
            $customerDevice = CustomerDevice::with('deviceModel.brand')
                ->findOrFail($data['customer_device_id']);
            $now = now();
            $termsAccepted = (bool) ($data['terms_accepted'] ?? false);
            $estimatedCost = (float) ($data['estimated_cost'] ?? 0);
            $downpayment = (float) ($data['downpayment'] ?? 0);

            $ticket = $this->tickets->create([
                ...$data,
                'ticket_number' => $this->generateTicketNumber($data['branch_id'], $now),
                'claim_code' => $this->generateClaimCode(),
                'device_brand_snapshot' => $customerDevice->deviceModel?->brand?->name,
                'device_model_snapshot' => $customerDevice->deviceModel?->name,
                'device_color_snapshot' => $customerDevice->color,
                'status' => 'received',
                'balance' => round($estimatedCost - $downpayment, 2),
                'terms_accepted' => $termsAccepted,
                'terms_accepted_at' => $termsAccepted ? $now : null,
            ]);

            VerificationToken::create(['repair_ticket_id' => $ticket->id]);

            $this->events->record($ticket, 'ticket_created', $actor, toStatus: 'received');

            return $ticket->fresh();
        });
    }

    /**
     * Pre-release edits only — Released tickets are locked (module 4: "Ticket
     * moves to Released, locked from further edits except warranty claims").
     */
    public function update(RepairTicket $ticket, array $data, User $actor): RepairTicket
    {
        if ($ticket->status === 'released') {
            throw new ApiException(ErrorCode::InvalidStatusTransition, 'Released tickets cannot be edited.');
        }

        $ticket = $this->tickets->update($ticket, $data);

        if (array_intersect(['downpayment', 'estimated_cost', 'approved_amount'], array_keys($data))) {
            $this->recalculateBalance($ticket);
        }

        $this->events->record($ticket, 'ticket_updated', $actor, note: 'Ticket details updated.');

        return $ticket->fresh();
    }

    public function transition(RepairTicket $ticket, string $toStatus, ?string $note, User $actor): RepairTicket
    {
        return DB::transaction(function () use ($ticket, $toStatus, $note, $actor) {
            /** @var RepairTicket $locked */
            $locked = RepairTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            TicketStateMachine::assertCanTransition($locked->status, $toStatus);

            if ($toStatus === 'released') {
                $this->assertImeiClearedForRelease($locked);
                $this->assertBalanceSettledForRelease($locked);
            }

            $from = $locked->status;
            $locked->update(['status' => $toStatus]);

            $this->events->record($locked, 'status_changed', $actor, $from, $toStatus, $note);

            return $locked->fresh();
        });
    }

    /**
     * The IMEI half of the release guard flagged in TicketStateMachine's
     * docblock (chain of custody, docs/design/01-domain-design.md §2.5) —
     * a settled balance (Stage 8 / POS) is still the other, open half.
     */
    private function assertImeiClearedForRelease(RepairTicket $ticket): void
    {
        $cleared = $ticket->imeiVerifications()
            ->where('phase', 'release')
            ->where(function ($query) {
                $query->where('matches_expected', true)->orWhereNotNull('overridden_by');
            })
            ->exists();

        if (! $cleared) {
            throw new ApiException(
                ErrorCode::ImeiMismatch,
                'Releasing this ticket requires a matching IMEI verification at the release phase (or an owner override).',
            );
        }
    }

    /**
     * The other half of the release guard (Stage 8 / POS payments) —
     * closes the gap TicketStateMachine's docblock has flagged open since
     * Stage 5. `balance` is kept current by recalculateBalance() on every
     * payment and every edit that touches the amounts it derives from.
     */
    private function assertBalanceSettledForRelease(RepairTicket $ticket): void
    {
        if ((float) $ticket->balance > 0.01) {
            throw new ApiException(
                ErrorCode::PaymentSumMismatch,
                "Releasing this ticket requires a settled balance — {$ticket->balance} is still due.",
            );
        }
    }

    public function addLine(RepairTicket $ticket, array $data, User $actor): TicketLine
    {
        return DB::transaction(function () use ($ticket, $data, $actor) {
            $amount = round((float) $data['quantity'] * (float) $data['unit_price'], 2);

            $line = TicketLine::create([
                'repair_ticket_id' => $ticket->id,
                'line_type' => $data['line_type'],
                'product_id' => $data['product_id'] ?? null,
                'service_id' => $data['service_id'] ?? null,
                'description' => $data['description'],
                'quantity' => $data['quantity'],
                'unit_cost' => $data['unit_cost'] ?? null,
                'unit_price' => $data['unit_price'],
                'amount' => $amount,
            ]);

            $this->events->record(
                $ticket,
                'line_added',
                $actor,
                note: $data['description'],
                metadata: ['line_id' => $line->id, 'line_type' => $data['line_type'], 'amount' => $amount],
            );

            return $line;
        });
    }

    public function lines(RepairTicket $ticket)
    {
        return $ticket->lines()->with(['product', 'service'])->latest()->get();
    }

    /** Cursor-paginated per Rule ("cursor pagination on ledger and timeline endpoints"). */
    public function events(RepairTicket $ticket): CursorPaginator
    {
        return $ticket->events()->with('actor')->orderByDesc('created_at')->cursorPaginate(20);
    }

    /**
     * balance = base cost − the intake-time downpayment − every payment
     * recorded since (App\Http\Controllers\Api\V1\TicketPaymentController).
     * `downpayment` stays a separate column rather than becoming a Payment
     * row retroactively — it predates POS/shifts existing at ticket intake.
     */
    public function recalculateBalance(RepairTicket $ticket): RepairTicket
    {
        $base = (float) ($ticket->approved_amount ?? $ticket->estimated_cost ?? 0);
        $paid = (float) Payment::where('payable_type', 'repair_ticket')->where('payable_id', $ticket->id)->sum('amount');
        $ticket->update(['balance' => round($base - (float) $ticket->downpayment - $paid, 2)]);

        return $ticket->fresh();
    }

    private function generateTicketNumber(int $branchId, Carbon $at): string
    {
        $branch = Branch::findOrFail($branchId);
        $n = Sequence::next($branchId, 'ticket', (int) $at->format('Y'), (int) $at->format('m'));

        return sprintf('JO-%s-%s-%04d', $branch->code, $at->format('Ym'), $n);
    }

    private function generateClaimCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (RepairTicket::where('claim_code', $code)->exists());

        return $code;
    }
}
