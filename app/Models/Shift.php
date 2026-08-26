<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasUlid;
use App\Models\Scopes\BranchScope;
use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['branch_id', 'cashier_id', 'opened_at', 'opening_float', 'closed_at', 'counted_cash', 'expected_cash', 'variance', 'notes'])]
#[ScopedBy(BranchScope::class)]
class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use BelongsToBranch, HasFactory, HasUlid;

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'opening_float' => 'decimal:2',
            'closed_at' => 'datetime',
            'counted_cash' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'variance' => 'decimal:2',
        ];
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }
}
