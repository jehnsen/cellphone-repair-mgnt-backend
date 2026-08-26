<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Database\Factories\TicketQuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['repair_ticket_id', 'quoted_amount', 'sent_at', 'channel', 'responded_at', 'decision', 'responder_note'])]
class TicketQuote extends Model
{
    /** @use HasFactory<TicketQuoteFactory> */
    use HasFactory, HasUlid;

    protected function casts(): array
    {
        return [
            'quoted_amount' => 'decimal:2',
            'sent_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function repairTicket(): BelongsTo
    {
        return $this->belongsTo(RepairTicket::class);
    }
}
