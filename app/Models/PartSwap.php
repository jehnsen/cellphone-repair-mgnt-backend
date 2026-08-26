<?php

namespace App\Models;

use Database\Factories\PartSwapFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'repair_ticket_id', 'removed_description', 'removed_serial', 'removed_photo_ref',
    'installed_product_id', 'installed_serial', 'disposition', 'technician_id',
])]
class PartSwap extends Model
{
    /** @use HasFactory<PartSwapFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public function repairTicket(): BelongsTo
    {
        return $this->belongsTo(RepairTicket::class);
    }

    public function installedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'installed_product_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}
