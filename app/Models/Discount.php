<?php

namespace App\Models;

use Database\Factories\DiscountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sale_id', 'sale_line_id', 'type', 'value', 'scope', 'id_type', 'id_number', 'cardholder_name', 'signature_ref'])]
class Discount extends Model
{
    /** @use HasFactory<DiscountFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public const TYPES = ['percent', 'amount', 'senior_citizen', 'pwd'];

    protected function casts(): array
    {
        return ['value' => 'decimal:2'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function saleLine(): BelongsTo
    {
        return $this->belongsTo(SaleLine::class);
    }
}
