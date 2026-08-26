<?php

namespace App\Models;

use Database\Factories\VerificationTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['repair_ticket_id', 'token', 'revoked_at'])]
class VerificationToken extends Model
{
    /** @use HasFactory<VerificationTokenFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['revoked_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->token)) {
                $model->token = Str::random(32);
            }
        });
    }

    public function repairTicket(): BelongsTo
    {
        return $this->belongsTo(RepairTicket::class);
    }
}
