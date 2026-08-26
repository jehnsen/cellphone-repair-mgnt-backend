<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasUlid;
use App\Models\Scopes\BranchScope;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only ledger — the source of truth for stock on hand (Rule 3).
 * Never updated or deleted; corrections are new, signed, reversing rows.
 */
#[Fillable([
    'product_id', 'branch_id', 'serialized_unit_id', 'quantity', 'unit_cost', 'movement_type',
    'reference_type', 'reference_id', 'reason_code', 'actor_id', 'balance_after', 'occurred_at',
])]
#[ScopedBy(BranchScope::class)]
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use BelongsToBranch, HasFactory, HasUlid;

    public const UPDATED_AT = null;

    public const TYPES = [
        'receipt', 'sale', 'return_in', 'return_out', 'ticket_consumption',
        'adjustment', 'transfer_in', 'transfer_out', 'write_off',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function serializedUnit(): BelongsTo
    {
        return $this->belongsTo(SerializedUnit::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function reference(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }
}
