<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'branch_id', 'business_date', 'gross_sales', 'discount_total', 'vat_total', 'net_sales',
    'cogs', 'gross_margin', 'tickets_received', 'tickets_released', 'refunds_total',
])]
class DailyMetric extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'gross_sales' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'net_sales' => 'decimal:2',
            'cogs' => 'decimal:2',
            'gross_margin' => 'decimal:2',
            'refunds_total' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
