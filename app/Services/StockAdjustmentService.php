<?php

namespace App\Services;

use App\Models\StockAdjustment;
use App\Models\StockAdjustmentLine;
use App\Models\User;
use App\Repositories\Contracts\StockAdjustmentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Corrections are new, signed rows — there's no update/delete on an
 * adjustment once posted, same append-only philosophy as stock_movements
 * itself (a bad adjustment is fixed with an opposite one, not edited).
 */
class StockAdjustmentService
{
    public function __construct(
        private readonly StockAdjustmentRepositoryInterface $adjustments,
        private readonly StockMovementRecorder $movements,
    ) {}

    public function list(): LengthAwarePaginator
    {
        return $this->adjustments->paginate();
    }

    public function create(array $data, User $actor): StockAdjustment
    {
        return DB::transaction(function () use ($data, $actor) {
            $adjustment = $this->adjustments->create([
                'branch_id' => $data['branch_id'],
                'reason_code' => $data['reason_code'],
                'note' => $data['note'] ?? null,
                'created_by' => $actor->id,
            ]);

            foreach ($data['lines'] as $line) {
                StockAdjustmentLine::create([
                    'stock_adjustment_id' => $adjustment->id,
                    'product_id' => $line['product_id'],
                    'serialized_unit_id' => $line['serialized_unit_id'] ?? null,
                    'quantity_delta' => $line['quantity_delta'],
                    'unit_cost' => $line['unit_cost'],
                ]);

                $this->movements->record(
                    productId: $line['product_id'],
                    branchId: $adjustment->branch_id,
                    quantity: (float) $line['quantity_delta'],
                    unitCost: (float) $line['unit_cost'],
                    movementType: 'adjustment',
                    actorId: $actor->id,
                    serializedUnitId: $line['serialized_unit_id'] ?? null,
                    referenceType: 'stock_adjustment',
                    referenceId: $adjustment->id,
                    reasonCode: $data['reason_code'],
                );
            }

            return $adjustment->fresh([
                'lines.product',
                'creator' => fn ($query) => $query->withoutGlobalScopes(),
            ]);
        });
    }
}
