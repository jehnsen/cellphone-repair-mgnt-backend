<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Database\Factories\InstallmentPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sale_id', 'principal', 'downpayment', 'term_months', 'schedule_rule', 'status'])]
class InstallmentPlan extends Model
{
    /** @use HasFactory<InstallmentPlanFactory> */
    use HasFactory, HasUlid;

    public const STATUSES = ['active', 'completed', 'defaulted', 'cancelled'];

    protected function casts(): array
    {
        return [
            'principal' => 'decimal:2',
            'downpayment' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(InstallmentSchedule::class);
    }
}
