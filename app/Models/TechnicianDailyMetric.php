<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['branch_id', 'technician_id', 'business_date', 'tickets_received', 'tickets_released', 'average_turnaround_minutes'])]
class TechnicianDailyMetric extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['business_date' => 'date'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}
