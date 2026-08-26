<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Database\Factories\UnclaimedNoticeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['repair_ticket_id', 'stage', 'generated_at', 'delivered_at', 'method', 'notice_payload'])]
class UnclaimedNotice extends Model
{
    /** @use HasFactory<UnclaimedNoticeFactory> */
    use HasFactory, HasUlid;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'delivered_at' => 'datetime',
            'notice_payload' => 'array',
        ];
    }

    public function repairTicket(): BelongsTo
    {
        return $this->belongsTo(RepairTicket::class);
    }
}
