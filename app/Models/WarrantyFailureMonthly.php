<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id', 'supplier_id', 'month', 'units_installed',
    'units_failed_within_30', 'units_failed_within_60', 'units_failed_within_90', 'failure_rate',
])]
class WarrantyFailureMonthly extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'failure_rate' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
