<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CustomerDevice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\RepairTicket;
use App\Models\Sequence;
use App\Models\StockLevel;
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
        private readonly StockMovementRecorder $movements,
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
                $this->assertBalanceSettledForRelease($locked);
            }

            $from = $locked->status;
            $locked->update(['status' => $toStatus]);

            $this->events->record($locked, 'status_changed', $actor, $from, $toStatus, $note);

            return $locked->fresh();
        });
    }

    /**
     * The release guard (Stage 8 / POS payments) — closes the gap
     * TicketStateMachine's docblock has flagged open since Stage 5.
     * `balance` is kept current by recalculateBalance() on every payment
     * and every edit that touches the amounts it derives from.
     *
     * There used to be an IMEI half to this guard too (a matching or
     * overridden release-phase verification, required before release) —
     * deliberately removed: shops found it blocked real releases whenever
     * staff hadn't run a release-phase scan (or the device's own IMEI
     * never passed Luhn to begin with, see ValidImei/Imei::isValid()), and
     * the business call was that IMEI verification stays a documentation
     * tool, not a release gate. imei-verifications/override still record
     * chain-of-custody exactly as before; they just no longer block
     * anything. See ImeiVerificationController, ImeiVerificationService.
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

    /**
     * A `part` line both bills the customer (TicketLine, same as before)
     * and — the piece flagged open since Stage 5 — consumes the part from
     * inventory: a stock check up front (mirrors
     * SaleService::resolveProductLine()) and a `ticket_consumption`
     * movement via StockMovementRecorder, in the same transaction as the
     * line itself. `line.stock_movement_id` (a column that has sat unused
     * since the Stage 5 migration, anticipating exactly this) links the
     * two rows. `labor` lines never touch stock — the CHECK constraint on
     * ticket_lines already guarantees a `labor` line has no product_id.
     * Untracked products (`track_inventory=false`) are skipped, same as
     * POS — nothing to check or ledger for those.
     */
    public function addLine(RepairTicket $ticket, array $data, User $actor): TicketLine
    {
        return DB::transaction(function () use ($ticket, $data, $actor) {
            $amount = round((float) $data['quantity'] * (float) $data['unit_price'], 2);
            $quantity = (float) $data['quantity'];

            $product = isset($data['product_id']) ? Product::find($data['product_id']) : null;

            if ($product?->track_inventory) {
                $level = StockLevel::withoutGlobalScopes()
                    ->where('product_id', $product->id)
                    ->where('branch_id', $ticket->branch_id)
                    ->first();
                $available = $level ? (float) $level->on_hand_qty - (float) $level->reserved_qty : 0.0;

                if ($available < $quantity) {
                    throw new ApiException(
                        ErrorCode::InsufficientStock,
                        "Only {$available} of \"{$product->name}\" available.",
                    );
                }
            }

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

            if ($product?->track_inventory) {
                $movement = $this->movements->record(
                    productId: $product->id,
                    branchId: $ticket->branch_id,
                    quantity: -$quantity,
                    unitCost: (float) ($data['unit_cost'] ?? $product->cost),
                    movementType: 'ticket_consumption',
                    actorId: $actor->id,
                    referenceType: 'ticket_line',
                    referenceId: $line->id,
                );

                $line->update(['stock_movement_id' => $movement->id]);
            }

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
