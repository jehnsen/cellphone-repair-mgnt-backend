<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['branch_id', 'snapshot_date', 'total_cost_value', 'total_retail_value', 'sku_count'])]
class InventoryValuationSnapshot extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'total_cost_value' => 'decimal:2',
            'total_retail_value' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
