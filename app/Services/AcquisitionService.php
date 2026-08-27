<?php

namespace App\Services;

use App\Models\Acquisition;
use App\Models\User;
use App\Repositories\Contracts\AcquisitionRepositoryInterface;
use App\Support\Api\ApiException;
use App\Support\Api\ErrorCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Buy-back intake. `Acquisition` has no product_id of its own — at intake
 * the shop only knows the seller/IMEI/offered price; the exact
 * product/model is identified at complete() time, once staff have
 * physically inspected the device, matching the design brief's
 * `resulting_serialized_unit_id` field (nullable until then).
 */
class AcquisitionService
{
    public function __construct(
        private readonly AcquisitionRepositoryInterface $acquisitions,
        private readonly SerializedUnitService $units,
    ) {}

    public function list(): LengthAwarePaginator
    {
        return $this->acquisitions->paginate();
    }

    public function create(array $data, User $actor): Acquisition
    {
        return $this->acquisitions->create([
            ...$data,
            'imei_check_result' => 'not_checked',
            'imei_checked_at' => null,
            'processed_by' => $actor->id,
        ]);
    }

    public function update(Acquisition $acquisition, array $data): Acquisition
    {
        if ($acquisition->resulting_serialized_unit_id !== null) {
            throw new ApiException(ErrorCode::InvalidStatusTransition, 'This acquisition is already completed and can no longer be edited.');
        }

        return $this->acquisitions->update($acquisition, $data)->fresh();
    }

    public function imeiCheck(Acquisition $acquisition, array $data): Acquisition
    {
        return $this->acquisitions->update($acquisition, [
            'imei_check_result' => $data['result'],
            'imei_checked_at' => now(),
        ])->fresh();
    }

    /**
     * Turns the acquisition into a real, sellable serialized unit — the
     * design brief's own guard: never while imei_check_result is flagged
     * (enforced here, not a DB CHECK, since it depends on another column's
     * value at a specific point in time, not a static row constraint).
     */
    public function complete(Acquisition $acquisition, array $data, User $actor): Acquisition
    {
        return DB::transaction(function () use ($acquisition, $data, $actor) {
            if ($acquisition->resulting_serialized_unit_id !== null) {
                throw new ApiException(ErrorCode::InvalidStatusTransition, 'This acquisition has already been completed.');
            }

            if ($acquisition->imei_check_result === 'flagged') {
                throw new ApiException(ErrorCode::AcquisitionImeiFlagged);
            }

            $unit = $this->units->create([
                'product_id' => $data['product_id'],
                'branch_id' => $acquisition->branch_id,
                'imei' => $acquisition->imei,
                'serial_number' => null,
                'condition' => $data['condition'],
                'acquisition_cost' => $acquisition->offered_price,
                'acquisition_source' => 'buyback',
                'warranty_terms' => $data['warranty_terms'] ?? null,
            ], $actor);

            $acquisition->update(['resulting_serialized_unit_id' => $unit->id]);

            return $acquisition->fresh('resultingSerializedUnit');
        });
    }
}
