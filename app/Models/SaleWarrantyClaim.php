<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasUlid;
use App\Models\Scopes\BranchScope;
use Database\Factories\SaleWarrantyClaimFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A customer availing a sale warranty. Lives under CP units — it never
 * creates a repair ticket. `handling = repair_board` only pins an existing
 * job order for whoever does the bench work; the claim itself is still the
 * sales-side record of what was promised and how it was settled.
 */
#[Fillable([
    'branch_id', 'sale_warranty_id', 'serialized_unit_id', 'reported_defect', 'handling',
    'repair_ticket_id', 'within_coverage', 'status', 'resolution', 'outcome_notes',
    'filed_by', 'resolved_by', 'resolved_at',
])]
#[ScopedBy(BranchScope::class)]
class SaleWarrantyClaim extends Model
{
    /** @use HasFactory<SaleWarrantyClaimFactory> */
    use BelongsToBranch, HasFactory, HasUlid;

    public const HANDLINGS = ['separate', 'repair_board'];

    public const STATUSES = ['open', 'resolved', 'rejected'];

    public const RESOLUTIONS = [
        'repaired_in_house', 'replaced', 'returned_to_supplier', 'refunded', 'rejected',
    ];

    protected function casts(): array
    {
        return [
            'within_coverage' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function warranty(): BelongsTo
    {
        return $this->belongsTo(SaleWarranty::class, 'sale_warranty_id');
    }

    public function serializedUnit(): BelongsTo
    {
        return $this->belongsTo(SerializedUnit::class);
    }

    public function repairTicket(): BelongsTo
    {
        return $this->belongsTo(RepairTicket::class);
    }

    public function filedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filed_by');
    }

    public function supplierReturn(): HasOne
    {
        return $this->hasOne(SupplierReturn::class);
    }
}
