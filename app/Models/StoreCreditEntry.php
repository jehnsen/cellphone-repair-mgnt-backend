<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Database\Factories\StoreCreditEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only — a correction is a new, opposite-direction row, never an update. */
#[Fillable([
    'store_credit_account_id', 'direction', 'amount', 'balance_after',
    'reason', 'reference_type', 'reference_id', 'actor_id',
])]
class StoreCreditEntry extends Model
{
    /** @use HasFactory<StoreCreditEntryFactory> */
    use HasFactory, HasUlid;

    public const UPDATED_AT = null;

    public const DIRECTIONS = ['credit', 'debit'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(StoreCreditAccount::class, 'store_credit_account_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
