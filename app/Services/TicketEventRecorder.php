<?php

namespace App\Services;

use App\Models\RepairTicket;
use App\Models\TicketEvent;
use App\Models\User;

/**
 * Every mutation anywhere in the ticket lifecycle writes one of these
 * (docs/design/01-domain-design.md §2.4) — append-only, never updated.
 */
class TicketEventRecorder
{
    public function record(
        RepairTicket $ticket,
        string $eventType,
        ?User $actor = null,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $note = null,
        array $metadata = [],
    ): TicketEvent {
        return TicketEvent::create([
            'repair_ticket_id' => $ticket->id,
            'actor_id' => $actor?->id,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
            'metadata' => $metadata,
        ]);
    }
}
