<?php

namespace App\Services;

use App\Models\Acquisition;
use App\Models\RefurbJob;
use App\Models\RefurbJobLine;
use App\Models\User;
use App\Repositories\Contracts\RefurbJobRepositoryInterface;
use App\Support\Api\ApiException;
use App\Support\Api\ErrorCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * A refurb job takes a just-acquired serialized unit out of sellable
 * circulation (`for_repair`) while parts/labor are put into it, then back
 * to `in_stock` on completion — same status the unit would carry while a
 * technician works a normal repair ticket. `landed_cost` (and, on
 * completion, the unit's own `acquisition_cost`) is recomputed from
 * labor + parts + the original acquisition price every time a line is added.
 */
class RefurbJobService
{
    public function __construct(
        private readonly RefurbJobRepositoryInterface $jobs,
        private readonly StockMovementRecorder $movements,
    ) {}

    public function list(): LengthAwarePaginator
    {
        return $this->jobs->paginate();
    }

    public function create(array $data, Acquisition $acquisition): RefurbJob
    {
        return DB::transaction(function () use ($data, $acquisition) {
            if ($acquisition->resulting_serialized_unit_id === null) {
                throw new ApiException(ErrorCode::ValidationFailed, 'This acquisition has not been completed into a serialized unit yet.');
            }

            $unit = $acquisition->resultingSerializedUnit;
            $unit->transitionStatus('in_stock', 'for_repair');

            $job = $this->jobs->create([
                'acquisition_id' => $acquisition->id,
                'serialized_unit_id' => $unit->id,
                'labor_cost' => $data['labor_cost'] ?? 0,
                'parts_cost' => 0,
                'landed_cost' => round((float) ($data['labor_cost'] ?? 0) + (float) $acquisition->offered_price, 2),
                'status' => 'open',
            ]);

            return $job->fresh(['acquisition', 'serializedUnit.product']);
        });
    }

    public function addLine(RefurbJob $job, array $data, User $actor): RefurbJobLine
    {
        return DB::transaction(function () use ($job, $data, $actor) {
            if ($job->status !== 'open') {
                throw new ApiException(ErrorCode::InvalidStatusTransition, 'This refurb job is already completed.');
            }

            $movement = $this->movements->record(
                productId: $data['product_id'],
                branchId: $job->serializedUnit->branch_id,
                quantity: -(float) $data['quantity'],
                unitCost: (float) $data['unit_cost'],
                movementType: 'refurb_consumption',
                actorId: $actor->id,
                referenceType: 'refurb_job',
                referenceId: $job->id,
            );

            $line = RefurbJobLine::create([
                'refurb_job_id' => $job->id,
                'product_id' => $data['product_id'],
                'stock_movement_id' => $movement->id,
                'quantity' => $data['quantity'],
                'unit_cost' => $data['unit_cost'],
            ]);

            $this->recalculateLandedCost($job->fresh());

            return $line->load('product');
        });
    }

    public function complete(RefurbJob $job): RefurbJob
    {
        return DB::transaction(function () use ($job) {
            if ($job->status !== 'open') {
                throw new ApiException(ErrorCode::InvalidStatusTransition, 'This refurb job is already completed.');
            }

            $unit = $job->serializedUnit;
            $unit->transitionStatus('for_repair', 'in_stock');
            $unit->update(['acquisition_cost' => $job->landed_cost]);

            $job->update(['status' => 'completed', 'completed_at' => now()]);

            return $job->fresh(['acquisition', 'serializedUnit.product', 'lines.product']);
        });
    }

    private function recalculateLandedCost(RefurbJob $job): void
    {
        $partsCost = round((float) RefurbJobLine::where('refurb_job_id', $job->id)->sum(DB::raw('quantity * unit_cost')), 2);
        $landed = round($partsCost + (float) $job->labor_cost + (float) $job->acquisition->offered_price, 2);

        $job->update(['parts_cost' => $partsCost, 'landed_cost' => $landed]);
    }
}
