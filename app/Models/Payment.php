<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** Append-only — a correction is a new, signed, reversing row, never an update. */
#[Fillable(['payable_type', 'payable_id', 'method', 'amount', 'reference_number', 'tendered', 'change_given', 'shift_id', 'actor_id'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasUlid, LogsActivity;

    public const UPDATED_AT = null;

    public const METHODS = ['cash', 'gcash', 'maya', 'card', 'bank_transfer', 'store_credit', 'trade_in'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tendered' => 'decimal:2',
            'change_given' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->dontLogEmptyChanges();
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function payable(): Sale|RepairTicket|null
    {
        return match ($this->payable_type) {
            'sale' => Sale::find($this->payable_id),
            'repair_ticket' => RepairTicket::find($this->payable_id),
            default => null,
        };
    }
}
