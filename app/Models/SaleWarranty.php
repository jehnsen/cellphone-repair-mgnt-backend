<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasUlid;
use App\Models\Scopes\BranchScope;
use Database\Factories\SaleWarrantyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The warranty a serialized unit carries out the door when it's sold —
 * distinct from `Warranty`, which only ever attaches to a repair ticket.
 * Issued automatically by SaleWarrantyService as each serialized-unit sale
 * line is written.
 */
#[Fillable([
    'branch_id', 'sale_id', 'sale_line_id', 'serialized_unit_id', 'customer_id', 'coverage',
    'term_days', 'starts_at', 'expiry_date', 'warranty_code', 'terms', 'exclusions', 'voided_at',
])]
#[ScopedBy(BranchScope::class)]
class SaleWarranty extends Model
{
    /** @use HasFactory<SaleWarrantyFactory> */
    use BelongsToBranch, HasFactory, HasUlid;

    public const COVERAGES = ['shop', 'manufacturer'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'expiry_date' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->voided_at === null && ! $this->expiry_date->isPast();
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function saleLine(): BelongsTo
    {
        return $this->belongsTo(SaleLine::class);
    }

    public function serializedUnit(): BelongsTo
    {
        return $this->belongsTo(SerializedUnit::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(SaleWarrantyClaim::class);
    }
}
