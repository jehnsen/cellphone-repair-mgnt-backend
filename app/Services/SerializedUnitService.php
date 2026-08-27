<?php

namespace App\Services;

use App\Models\SerializedUnit;
use App\Models\User;
use App\Repositories\Contracts\SerializedUnitRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SerializedUnitService
{
    public function __construct(
        private readonly SerializedUnitRepositoryInterface $units,
        private readonly StockMovementRecorder $movements,
    ) {}

    public function list(): LengthAwarePaginator
    {
        return $this->units->paginate();
    }

    /**
     * Registering a unit is treated as an ad-hoc receipt into inventory —
     * it moves stock_levels/stock_movements the same as a formal goods
     * receipt would (Stage 7 will supersede this with the real
     * purchase-order/goods-receipt flow and a proper `reference`, but the
     * ledger effect is identical either way).
     */
    public function create(array $data, User $actor): SerializedUnit
    {
        return DB::transaction(function () use ($data, $actor) {
            $unit = $this->units->create($data);

            $this->movements->record(
                productId: $unit->product_id,
                branchId: $unit->branch_id,
                quantity: 1,
                unitCost: (float) $unit->acquisition_cost,
                movementType: 'receipt',
                actorId: $actor->id,
                serializedUnitId: $unit->id,
            );

            return $unit->fresh();
        });
    }

    /**
     * Plain field edits (condition, grade, warranty terms, ...) go straight
     * through. A `status` change is compare-and-swap guarded by
     * `SerializedUnit::transitionStatus()` (Rule 4: a unit can only be sold
     * once); flipping to `written_off` additionally records a -1 stock
     * movement so stock_levels stays in sync with the true in-stock count.
     * `sold` is rejected by the Form Request — that belongs to the sales
     * flow (Stage 8), which will record its own `sale`-referenced movement.
     */
    public function update(SerializedUnit $unit, array $data, User $actor): SerializedUnit
    {
        if (! isset($data['status']) || $data['status'] === $unit->status) {
            return $this->units->update($unit, $data);
        }

        return DB::transaction(function () use ($unit, $data, $actor) {
            $from = $unit->status;
            $to = $data['status'];
            unset($data['status']);

            $unit->transitionStatus($from, $to);

            if ($to === 'written_off') {
                $this->movements->record(
                    productId: $unit->product_id,
                    branchId: $unit->branch_id,
                    quantity: -1,
                    unitCost: (float) $unit->acquisition_cost,
                    movementType: 'write_off',
                    actorId: $actor->id,
                    serializedUnitId: $unit->id,
                );
            }

            if ($data !== []) {
                $unit = $this->units->update($unit, $data);
            }

            return $unit->fresh();
        });
    }
}
