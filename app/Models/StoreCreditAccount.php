<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Database\Factories\StoreCreditAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shop-wide store-credit balance for one customer. `balance` is a cached
 * running total maintained by StoreCreditService inside the same
 * transaction as each StoreCreditEntry — never authoritative on its own
 * (the append-only entries are), same pattern as stock_levels.
 */
#[Fillable(['customer_id', 'balance'])]
class StoreCreditAccount extends Model
{
    /** @use HasFactory<StoreCreditAccountFactory> */
    use HasFactory, HasUlid;

    protected function casts(): array
    {
        return ['balance' => 'decimal:2'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(StoreCreditEntry::class);
    }
}
