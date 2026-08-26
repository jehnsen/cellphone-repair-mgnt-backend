<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Database\Factories\RefurbJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['acquisition_id', 'serialized_unit_id', 'labor_cost', 'parts_cost', 'landed_cost', 'status', 'completed_at'])]
class RefurbJob extends Model
{
    /** @use HasFactory<RefurbJobFactory> */
    use HasFactory, HasUlid;

    protected function casts(): array
    {
        return [
            'labor_cost' => 'decimal:2',
            'parts_cost' => 'decimal:2',
            'landed_cost' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    public function acquisition(): BelongsTo
    {
        return $this->belongsTo(Acquisition::class);
    }

    public function serializedUnit(): BelongsTo
    {
        return $this->belongsTo(SerializedUnit::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RefurbJobLine::class);
    }
}
