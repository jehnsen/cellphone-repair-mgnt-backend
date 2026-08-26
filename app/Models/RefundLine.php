<?php

namespace App\Models;

use Database\Factories\RefundLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['refund_id', 'sale_line_id', 'quantity', 'amount', 'restock_behavior'])]
class RefundLine extends Model
{
    /** @use HasFactory<RefundLineFactory> */
    use HasFactory;

    public $timestamps = false;

    public const RESTOCK_BEHAVIORS = ['restock', 'no_restock', 'write_off'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function saleLine(): BelongsTo
    {
        return $this->belongsTo(SaleLine::class);
    }
}
