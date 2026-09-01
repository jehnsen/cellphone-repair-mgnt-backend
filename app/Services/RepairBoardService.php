<?php

namespace App\Services;

use App\Models\RepairTicket;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Collection;

/**
 * The repair status board — every open ticket grouped into the columns
 * counter staff drag them across. Read-only; moving a ticket is still
 * POST /tickets/{ticket}/transition, which runs the state machine.
 *
 * Deliberately a thin projection, not RepairTicketResource: a board row
 * carries only what fits on a card. No pricing, no unlock code, no
 * customer address — the board is often on a screen the customer can see.
 *
 * Scope is BranchScope's as usual, so a cashier sees their own branch and
 * an owner can pull ?branch=all to watch both benches at once.
 */
class RepairBoardService
{
    /** Board columns, in the order they're displayed. Terminal statuses are excluded. */
    public const COLUMNS = [
        'received', 'diagnosed', 'awaiting_approval', 'awaiting_parts',
        'in_repair', 'qc', 'ready_for_pickup',
    ];

    public function __construct(private readonly BranchContext $context) {}

    /** @return array<string, mixed> */
    public function board(): array
    {
        $tickets = RepairTicket::query()
            ->whereIn('status', self::COLUMNS)
            ->with([
                // A repeat customer can belong to another branch, same as
                // the technician below — BranchScope would null them out.
                'customer' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'ulid', 'name'),
                // A ticket's technician can sit in another branch (they
                // cover for each other), and BranchScope would null them
                // out on a normal eager load — see CLAUDE.md.
                'assignedTechnician' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'ulid', 'name'),
                'branch:id,ulid,code,name',
            ])
            // Nulls last, so undated jobs don't crowd the top of a column.
            ->orderByRaw('promised_date IS NULL, promised_date')
            ->orderBy('created_at')
            ->get();

        $grouped = $tickets->groupBy('status');

        return [
            'scope' => $this->context->spansAllBranches() ? 'all_branches' : 'branch',
            'as_of' => now()->toIso8601String(),
            'columns' => collect(self::COLUMNS)
                ->map(fn (string $status) => [
                    'status' => $status,
                    'count' => $grouped->get($status, new Collection)->count(),
                    'tickets' => $this->cards($grouped->get($status, new Collection)),
                ])
                ->all(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function cards(Collection $tickets): array
    {
        return $tickets->map(fn (RepairTicket $ticket) => [
            'ulid' => $ticket->ulid,
            'ticket_number' => $ticket->ticket_number,
            'status' => $ticket->status,
            'customer_name' => $ticket->customer?->name,
            'device' => $this->deviceLabel($ticket),
            'reported_problem' => $ticket->reported_problem,
            'assigned_technician' => $ticket->assignedTechnician?->name,
            'promised_date' => $ticket->promised_date?->toDateString(),
            // Lets the board flag a job that's already late without the
            // client re-deriving it from promised_date in every timezone.
            'is_overdue' => $ticket->promised_date !== null
                && $ticket->promised_date->isPast()
                && $ticket->status !== 'ready_for_pickup',
            'created_at' => $ticket->created_at?->toIso8601String(),
            // Only meaningful on a cross-branch board; harmless otherwise.
            'branch' => [
                'ulid' => $ticket->branch?->ulid,
                'code' => $ticket->branch?->code,
            ],
        ])->values()->all();
    }

    /**
     * Built from the ticket's own snapshot columns rather than joining
     * through customer_devices -> device_models -> device_brands: the
     * snapshot is what the device was called at intake, which is what the
     * board should show, and it costs no extra queries.
     */
    private function deviceLabel(RepairTicket $ticket): ?string
    {
        $label = trim(implode(' ', array_filter([
            $ticket->device_brand_snapshot,
            $ticket->device_model_snapshot,
            $ticket->device_color_snapshot,
        ])));

        return $label !== '' ? $label : null;
    }
}
