<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sale_id', 'reason_code', 'processed_by'])]
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory, HasUlid;

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RefundLine::class);
    }
}
