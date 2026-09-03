<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasUlid;
use App\Models\Scopes\BranchScope;
use App\Support\Api\ApiException;
use App\Support\Api\ErrorCode;
use Database\Factories\SerializedUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id', 'imei', 'serial_number', 'condition', 'grade', 'acquisition_cost',
    'acquisition_source', 'status', 'branch_id', 'warranty_terms',
])]
#[ScopedBy(BranchScope::class)]
class SerializedUnit extends Model
{
    /** @use HasFactory<SerializedUnitFactory> */
    use BelongsToBranch, HasFactory, HasUlid;

    public const STATUSES = ['in_stock', 'reserved', 'sold', 'for_repair', 'written_off', 'returned_to_supplier'];

    public const CONDITIONS = ['brand_new', 'open_box', 'secondhand', 'refurbished'];

    protected function casts(): array
    {
        return ['acquisition_cost' => 'decimal:2'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Row-locks the unit and asserts its current status before flipping it,
     * per Rule 4 (a serialized unit can only be sold once). Callers must
     * already be inside a transaction.
     *
     * @throws ApiException with ErrorCode::UnitAlreadySold on mismatch
     */
    public function transitionStatus(string $expectedCurrent, string $next): void
    {
        $locked = self::whereKey($this->id)->lockForUpdate()->firstOrFail();

        if ($locked->status !== $expectedCurrent) {
            throw new ApiException(ErrorCode::UnitAlreadySold);
        }

        $locked->update(['status' => $next]);
        $this->status = $next;
    }
}
