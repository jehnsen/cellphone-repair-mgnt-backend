<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasUlid;
use App\Models\Scopes\BranchScope;
use Database\Factories\SupplierReturnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A serialized unit shipped back to its vendor — typically a factory
 * defect surfaced by a sale-warranty claim, but also usable for a
 * dead-on-arrival unit that never sold. Sending it writes a `return_out`
 * stock movement and moves the unit to `returned_to_supplier`; closing it
 * records what came back (a replacement unit or a credit).
 */
#[Fillable([
    'branch_id', 'supplier_id', 'serialized_unit_id', 'sale_warranty_claim_id', 'reason',
    'reason_note', 'status', 'replacement_serialized_unit_id', 'credit_amount', 'sent_at',
    'resolved_at', 'processed_by',
])]
#[ScopedBy(BranchScope::class)]
class SupplierReturn extends Model
{
    /** @use HasFactory<SupplierReturnFactory> */
    use BelongsToBranch, HasFactory, HasUlid;

    public const REASONS = ['factory_defect', 'dead_on_arrival', 'wrong_item', 'other'];

    public const STATUSES = ['sent', 'replaced', 'credited', 'rejected', 'closed'];

    /** Terminal states a close() can land on. */
    public const OUTCOMES = ['replaced', 'credited', 'rejected', 'closed'];

    protected function casts(): array
    {
        return [
            'credit_amount' => 'decimal:2',
            'sent_at' => 'date',
            'resolved_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function serializedUnit(): BelongsTo
    {
        return $this->belongsTo(SerializedUnit::class);
    }

    public function replacementSerializedUnit(): BelongsTo
    {
        return $this->belongsTo(SerializedUnit::class, 'replacement_serialized_unit_id');
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(SaleWarrantyClaim::class, 'sale_warranty_claim_id');
    }
}
