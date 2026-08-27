<?php

namespace App\Services;

use App\Models\PartSwap;
use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A documentation-only chain-of-custody record of what physically came out
 * of the device and what went in — not the billing line for it. Stock
 * consumption for the installed part still ties to `ticket_lines`
 * (deferred since Stage 5), not here, so a part swap never touches the
 * inventory ledger itself; wiring both to the same movement would
 * double-book it.
 */
class PartSwapService
{
    public function __construct(private readonly TicketEventRecorder $events) {}

    public function list(RepairTicket $ticket): Collection
    {
        return $ticket->partSwaps()
            ->with([
                'installedProduct',
                'technician' => fn ($query) => $query->withoutGlobalScopes(),
            ])
            ->latest()
            ->get();
    }

    public function record(RepairTicket $ticket, array $data, User $technician): PartSwap
    {
        return DB::transaction(function () use ($ticket, $data, $technician) {
            $swap = PartSwap::create([
                'repair_ticket_id' => $ticket->id,
                'removed_description' => $data['removed_description'],
                'removed_serial' => $data['removed_serial'] ?? null,
                'removed_photo_ref' => $data['removed_photo_ref'] ?? null,
                'installed_product_id' => $data['installed_product_id'],
                'installed_serial' => $data['installed_serial'] ?? null,
                'disposition' => $data['disposition'],
                'technician_id' => $technician->id,
            ]);

            $this->events->record(
                $ticket,
                'part_swapped',
                $technician,
                note: $data['removed_description'],
                metadata: ['swap_id' => $swap->id, 'disposition' => $data['disposition']],
            );

            return $swap;
        });
    }
}
