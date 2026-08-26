<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasUlid;
use App\Models\Scopes\BranchScope;
use Database\Factories\AcquisitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'branch_id', 'seller_name', 'seller_id_type', 'seller_id_number', 'seller_id_photo_ref',
    'declared_source', 'offered_price', 'imei', 'condition_assessment', 'purchase_date',
    'imei_check_result', 'imei_checked_at', 'resulting_serialized_unit_id', 'processed_by',
])]
#[ScopedBy(BranchScope::class)]
class Acquisition extends Model
{
    /** @use HasFactory<AcquisitionFactory> */
    use BelongsToBranch, HasFactory, HasUlid, LogsActivity;

    public const IMEI_CHECK_RESULTS = ['clear', 'flagged', 'not_checked'];

    protected function casts(): array
    {
        return [
            'offered_price' => 'decimal:2',
            'purchase_date' => 'date',
            'imei_checked_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty()->dontLogEmptyChanges();
    }

    public function resultingSerializedUnit(): BelongsTo
    {
        return $this->belongsTo(SerializedUnit::class, 'resulting_serialized_unit_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function refurbJob(): HasOne
    {
        return $this->hasOne(RefurbJob::class);
    }
}
