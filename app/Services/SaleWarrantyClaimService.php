<?php

namespace App\Services;

use App\Models\RepairTicket;
use App\Models\SaleWarranty;
use App\Models\SaleWarrantyClaim;
use App\Models\User;
use App\Repositories\Contracts\SaleWarrantyClaimRepositoryInterface;
use App\Support\Api\ApiException;
use App\Support\Api\ErrorCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * A customer availing a sale warranty. The claim is a sales-side record
 * start to finish — filing one never creates a repair ticket. When the
 * bench needs to touch the unit, `handling = repair_board` pins an
 * existing job order; the claim still owns the outcome.
 */
class SaleWarrantyClaimService
{
    public function __construct(private readonly SaleWarrantyClaimRepositoryInterface $claims) {}

    public function list(): LengthAwarePaginator
    {
        return $this->claims->paginate();
    }

    public function show(SaleWarrantyClaim $claim): SaleWarrantyClaim
    {
        return $claim->load([
            'warranty',
            'serializedUnit.product',
            'repairTicket' => fn ($query) => $query->withoutGlobalScopes(),
            'filedBy' => fn ($query) => $query->withoutGlobalScopes(),
            'supplierReturn',
        ]);
    }

    public function file(SaleWarranty $warranty, array $data, User $actor): SaleWarrantyClaim
    {
        if ($warranty->voided_at !== null) {
            throw new ApiException(ErrorCode::ValidationFailed, 'This warranty was voided with its sale and can no longer be claimed.');
        }

        $handling = $data['handling'] ?? 'separate';
        $repairTicketId = null;

        if ($handling === 'repair_board' && isset($data['repair_ticket_ulid'])) {
            $repairTicketId = RepairTicket::withoutGlobalScopes()
                ->where('ulid', $data['repair_ticket_ulid'])
                ->value('id');
        }

        $claim = $this->claims->create([
            'branch_id' => $warranty->branch_id,
            'sale_warranty_id' => $warranty->id,
            'serialized_unit_id' => $warranty->serialized_unit_id,
            'reported_defect' => $data['reported_defect'],
            'handling' => $handling,
            'repair_ticket_id' => $repairTicketId,
            'within_coverage' => $warranty->isActive(),
            'status' => 'open',
            'filed_by' => $actor->id,
        ]);

        return $this->show($claim);
    }

    public function resolve(SaleWarrantyClaim $claim, array $data, User $actor): SaleWarrantyClaim
    {
        if ($claim->status !== 'open') {
            throw new ApiException(ErrorCode::InvalidStatusTransition, 'This claim has already been closed.');
        }

        $resolution = $data['resolution'];

        $claim->update([
            'status' => $resolution === 'rejected' ? 'rejected' : 'resolved',
            'resolution' => $resolution,
            'outcome_notes' => $data['outcome_notes'] ?? null,
            'resolved_by' => $actor->id,
            'resolved_at' => now(),
        ]);

        return $this->show($claim->fresh());
    }
}
