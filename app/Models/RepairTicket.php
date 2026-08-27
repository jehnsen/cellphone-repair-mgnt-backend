<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasUlid;
use App\Models\Scopes\BranchScope;
use Database\Factories\RepairTicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'branch_id', 'customer_id', 'customer_device_id', 'ticket_number', 'claim_code',
    'device_brand_snapshot', 'device_model_snapshot', 'device_color_snapshot',
    'reported_problem', 'problem_tags', 'unlock_method', 'unlock_value',
    'accessories_turned_over', 'intake_condition_checklist', 'estimated_cost',
    'approved_amount', 'downpayment', 'balance', 'promised_date', 'assigned_technician_id',
    'status', 'warranty_days_offered', 'terms_accepted', 'terms_accepted_at',
])]
#[ScopedBy(BranchScope::class)]
class RepairTicket extends Model
{
    /** @use HasFactory<RepairTicketFactory> */
    use BelongsToBranch, HasFactory, HasUlid, LogsActivity;

    public const STATUSES = [
        'received', 'diagnosed', 'awaiting_approval', 'awaiting_parts', 'in_repair',
        'qc', 'ready_for_pickup', 'released', 'unrepairable', 'returned_as_is', 'unclaimed',
    ];

    protected function casts(): array
    {
        return [
            'problem_tags' => 'array',
            'unlock_method' => 'encrypted',
            'unlock_value' => 'encrypted',
            'accessories_turned_over' => 'array',
            'intake_condition_checklist' => 'array',
            'estimated_cost' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'downpayment' => 'decimal:2',
            'balance' => 'decimal:2',
            'promised_date' => 'date',
            'terms_accepted' => 'boolean',
            'terms_accepted_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'approved_amount', 'downpayment', 'balance', 'assigned_technician_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerDevice(): BelongsTo
    {
        return $this->belongsTo(CustomerDevice::class);
    }

    public function assignedTechnician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_technician_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(TicketLine::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(TicketPhoto::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(TicketQuote::class);
    }

    public function warranty(): HasOne
    {
        return $this->hasOne(Warranty::class);
    }

    public function imeiVerifications(): HasMany
    {
        return $this->hasMany(ImeiVerification::class);
    }

    public function partSwaps(): HasMany
    {
        return $this->hasMany(PartSwap::class);
    }

    public function verificationToken(): HasOne
    {
        return $this->hasOne(VerificationToken::class);
    }

    public function finding(): HasOne
    {
        return $this->hasOne(RepairFinding::class);
    }

    public function payments()
    {
        return Payment::where('payable_type', 'repair_ticket')->where('payable_id', $this->id);
    }
}
