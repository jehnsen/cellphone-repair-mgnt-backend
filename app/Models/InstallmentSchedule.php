<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Database\Factories\InstallmentScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['installment_plan_id', 'due_date', 'amount_due', 'amount_paid', 'status'])]
class InstallmentSchedule extends Model
{
    /** @use HasFactory<InstallmentScheduleFactory> */
    use HasFactory, HasUlid;

    public const STATUSES = ['pending', 'paid', 'overdue', 'waived'];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount_due' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    public function installmentPlan(): BelongsTo
    {
        return $this->belongsTo(InstallmentPlan::class);
    }
}
