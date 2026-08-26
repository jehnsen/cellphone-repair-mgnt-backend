<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Database\Factories\CommissionRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['branch_id', 'technician_id', 'role', 'basis', 'value', 'effective_from', 'effective_to'])]
class CommissionRule extends Model
{
    /** @use HasFactory<CommissionRuleFactory> */
    use HasFactory, HasUlid;

    public const BASES = ['flat', 'percent_of_labor', 'percent_of_margin'];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CommissionEntry::class);
    }
}
