<?php

namespace App\Models;

use Database\Factories\RefurbJobLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['refurb_job_id', 'product_id', 'stock_movement_id', 'quantity', 'unit_cost'])]
class RefurbJobLine extends Model
{
    /** @use HasFactory<RefurbJobLineFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function refurbJob(): BelongsTo
    {
        return $this->belongsTo(RefurbJob::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }
}
