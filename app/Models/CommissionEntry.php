<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Database\Factories\CommissionEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only — a reversal is a new signed row referencing the original. */
#[Fillable(['repair_ticket_id', 'technician_id', 'commission_rule_id', 'amount', 'status', 'reverses_entry_id', 'reversal_reason'])]
class CommissionEntry extends Model
{
    /** @use HasFactory<CommissionEntryFactory> */
    use HasFactory, HasUlid;

    public const UPDATED_AT = null;

    public const STATUSES = ['pending', 'payable', 'paid', 'reversed'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function repairTicket(): BelongsTo
    {
        return $this->belongsTo(RepairTicket::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function commissionRule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class);
    }

    public function reversesEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_entry_id');
    }
}
