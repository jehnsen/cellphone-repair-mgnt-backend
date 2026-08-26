<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Database\Factories\WarrantyClaimFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['warranty_id', 'child_ticket_id', 'fault_attribution', 'product_id'])]
class WarrantyClaim extends Model
{
    /** @use HasFactory<WarrantyClaimFactory> */
    use HasFactory, HasUlid;

    public function warranty(): BelongsTo
    {
        return $this->belongsTo(Warranty::class);
    }

    public function childTicket(): BelongsTo
    {
        return $this->belongsTo(RepairTicket::class, 'child_ticket_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
