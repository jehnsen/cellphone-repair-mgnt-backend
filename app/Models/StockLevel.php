<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Scopes\BranchScope;
use Database\Factories\StockLevelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cached balance derived from stock_movements — never authoritative. See
 * Rule 3 and the reconcile-check artisan command (added when the inventory
 * service layer ships).
 */
#[Fillable(['product_id', 'branch_id', 'on_hand_qty', 'reserved_qty'])]
#[ScopedBy(BranchScope::class)]
class StockLevel extends Model
{
    /** @use HasFactory<StockLevelFactory> */
    use BelongsToBranch, HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'on_hand_qty' => 'decimal:2',
            'reserved_qty' => 'decimal:2',
            'updated_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
