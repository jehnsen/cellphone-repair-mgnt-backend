<?php

namespace App\Models;

use Database\Factories\StockAdjustmentLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['stock_adjustment_id', 'product_id', 'serialized_unit_id', 'quantity_delta', 'unit_cost'])]
class StockAdjustmentLine extends Model
{
    /** @use HasFactory<StockAdjustmentLineFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'decimal:2',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function stockAdjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function serializedUnit(): BelongsTo
    {
        return $this->belongsTo(SerializedUnit::class);
    }
}
