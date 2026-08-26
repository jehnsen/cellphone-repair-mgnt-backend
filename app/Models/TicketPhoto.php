<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Database\Factories\TicketPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['repair_ticket_id', 'phase', 'storage_disk', 'storage_path', 'sha256_hash', 'captured_at', 'captured_by'])]
class TicketPhoto extends Model
{
    /** @use HasFactory<TicketPhotoFactory> */
    use HasFactory, HasUlid;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['captured_at' => 'datetime'];
    }

    public function repairTicket(): BelongsTo
    {
        return $this->belongsTo(RepairTicket::class);
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }
}
