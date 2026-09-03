<?php

namespace App\Services;

use App\Models\SaleWarrantyClaim;
use App\Models\SerializedUnit;
use App\Models\SupplierReturn;
use App\Models\User;
use App\Repositories\Contracts\SupplierReturnRepositoryInterface;
use App\Support\Api\ApiException;
use App\Support\Api\ErrorCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Shipping a serialized unit back to its vendor — the "ibalik mi supplier"
 * case for a factory defect. Sending it writes the stock ledger (a
 * `return_in` first if the unit had already been sold, so on-hand nets
 * correctly, then a `return_out`) and moves the unit to
 * `returned_to_supplier`. Closing records what the supplier gave back: a
 * replacement unit (minted here as a fresh receipt) or a credit amount.
 */
class SupplierReturnService
{
    public function __construct(
        private readonly SupplierReturnRepositoryInterface $returns,
        private readonly SerializedUnitService $units,
        private readonly StockMovementRecorder $movements,
    ) {}

    public function list(): LengthAwarePaginator
    {
        return $this->returns->paginate();
    }

    public function show(SupplierReturn $return): SupplierReturn
    {
        return $return->load([
            'supplier',
            'serializedUnit.product',
            'replacementSerializedUnit.product',
            'claim',
        ]);
    }

    public function create(array $data, User $actor): SupplierReturn
    {
        return DB::transaction(function () use ($data, $actor) {
            /** @var SerializedUnit $unit */
            $unit = SerializedUnit::with('product')->findOrFail($data['serialized_unit_id']);

            $returnable = ['in_stock', 'sold', 'for_repair'];
            if (! in_array($unit->status, $returnable, true)) {
                throw new ApiException(
                    ErrorCode::InvalidStatusTransition,
                    "A unit with status \"{$unit->status}\" cannot be sent back to a supplier.",
                );
            }

            $claimId = $data['sale_warranty_claim_id'] ?? null;

            $return = $this->returns->create([
                'branch_id' => $unit->branch_id,
                'supplier_id' => $data['supplier_id'],
                'serialized_unit_id' => $unit->id,
                'sale_warranty_claim_id' => $claimId,
                'reason' => $data['reason'],
                'reason_note' => $data['reason_note'] ?? null,
                'status' => 'sent',
                'sent_at' => now()->toDateString(),
                'processed_by' => $actor->id,
            ]);

            // A sold unit is already off the shelf — bring it back into our
            // hands on paper before shipping it out, so the net on-hand
            // effect of the round trip is zero rather than double-counting.
            if ($unit->status === 'sold') {
                $this->movements->record(
                    productId: $unit->product_id,
                    branchId: $unit->branch_id,
                    quantity: 1,
                    unitCost: (float) $unit->acquisition_cost,
                    movementType: 'return_in',
                    actorId: $actor->id,
                    serializedUnitId: $unit->id,
                    referenceType: 'supplier_return',
                    referenceId: $return->id,
                );
            }

            $unit->transitionStatus($unit->status, 'returned_to_supplier');

            $this->movements->record(
                productId: $unit->product_id,
                branchId: $unit->branch_id,
                quantity: -1,
                unitCost: (float) $unit->acquisition_cost,
                movementType: 'return_out',
                actorId: $actor->id,
                serializedUnitId: $unit->id,
                referenceType: 'supplier_return',
                referenceId: $return->id,
                reasonCode: $data['reason'],
            );

            return $this->show($return->fresh());
        });
    }

    public function close(SupplierReturn $return, array $data, User $actor): SupplierReturn
    {
        return DB::transaction(function () use ($return, $data, $actor) {
            if ($return->status !== 'sent') {
                throw new ApiException(ErrorCode::InvalidStatusTransition, 'This supplier return is already closed.');
            }

            $outcome = $data['outcome'];
            $update = ['status' => $outcome, 'resolved_at' => now()];

            if ($outcome === 'replaced') {
                $original = $return->serializedUnit;
                $replacement = $data['replacement'];

                $unit = $this->units->create([
                    'product_id' => $original->product_id,
                    'branch_id' => $return->branch_id,
                    'imei' => $replacement['imei'] ?? null,
                    'serial_number' => $replacement['serial_number'] ?? null,
                    'condition' => $replacement['condition'] ?? 'brand_new',
                    'acquisition_cost' => $replacement['acquisition_cost'] ?? 0,
                    'acquisition_source' => 'supplier_replacement',
                ], $actor);

                $update['replacement_serialized_unit_id'] = $unit->id;
            }

            if ($outcome === 'credited') {
                $update['credit_amount'] = $data['credit_amount'];
            }

            $return->update($update);

            // A supplier return born from a claim carries that claim to a
            // close too, unless someone already resolved it by hand.
            if ($return->sale_warranty_claim_id !== null) {
                $claim = SaleWarrantyClaim::find($return->sale_warranty_claim_id);

                if ($claim !== null && $claim->status === 'open') {
                    $claim->update([
                        'status' => $outcome === 'rejected' ? 'rejected' : 'resolved',
                        'resolution' => 'returned_to_supplier',
                        'outcome_notes' => $data['outcome_notes'] ?? null,
                        'resolved_by' => $actor->id,
                        'resolved_at' => now(),
                    ]);
                }
            }

            return $this->show($return->fresh());
        });
    }
}
