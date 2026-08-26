<?php

namespace App\Models;

use Database\Factories\ImeiVerificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['repair_ticket_id', 'phase', 'scanned_imei', 'matches_expected', 'actor_id', 'override_reason', 'overridden_by'])]
class ImeiVerification extends Model
{
    /** @use HasFactory<ImeiVerificationFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['matches_expected' => 'boolean'];
    }

    public function repairTicket(): BelongsTo
    {
        return $this->belongsTo(RepairTicket::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }
}
