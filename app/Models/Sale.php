<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasUlid;
use App\Models\Scopes\BranchScope;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'branch_id', 'customer_id', 'cashier_id', 'shift_id', 'subtotal', 'discount_total',
    'vat_amount', 'vatable_sales', 'vat_exempt_sales', 'zero_rated_sales', 'total',
    'status', 'void_reason', 'source', 'client_uuid',
])]
#[ScopedBy(BranchScope::class)]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use BelongsToBranch, HasFactory, HasUlid, LogsActivity;

    public const STATUSES = ['completed', 'voided', 'refunded', 'partially_refunded'];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'vatable_sales' => 'decimal:2',
            'vat_exempt_sales' => 'decimal:2',
            'zero_rated_sales' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'total'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(Discount::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function installmentPlan()
    {
        return $this->hasOne(InstallmentPlan::class);
    }

    public function payments()
    {
        return Payment::where('payable_type', 'sale')->where('payable_id', $this->id);
    }
}
