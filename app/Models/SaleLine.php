<?php

namespace App\Models;

use Database\Factories\SaleLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sale_id', 'sellable_type', 'sellable_id', 'quantity', 'unit_price', 'unit_cost', 'line_discount', 'amount'])]
class SaleLine extends Model
{
    /** @use HasFactory<SaleLineFactory> */
    use HasFactory;

    public $timestamps = false;

    public const SELLABLE_TYPES = ['product', 'serialized_unit', 'service', 'ticket_balance'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'line_discount' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** Resolves the polymorphic sellable — one of Product|SerializedUnit|Service|null (ticket_balance). */
    public function sellable(): Product|SerializedUnit|Service|null
    {
        return match ($this->sellable_type) {
            'product' => Product::find($this->sellable_id),
            'serialized_unit' => SerializedUnit::find($this->sellable_id),
            'service' => Service::find($this->sellable_id),
            default => null,
        };
    }
}
