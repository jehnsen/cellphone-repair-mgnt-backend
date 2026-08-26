<?php

namespace App\Models;

use Database\Factories\TicketEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only timeline — every mutation in the ticket lifecycle writes one. */
#[Fillable(['repair_ticket_id', 'actor_id', 'event_type', 'from_status', 'to_status', 'note', 'metadata'])]
class TicketEvent extends Model
{
    /** @use HasFactory<TicketEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function repairTicket(): BelongsTo
    {
        return $this->belongsTo(RepairTicket::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
