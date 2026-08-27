<?php

namespace App\Services;

use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Database\QueryException;

/**
 * The one place allowed to write `stock_movements` and keep `stock_levels`
 * in sync (Rule 3: stock_movements is the append-only source of truth;
 * stock_levels is a cached, never-authoritative balance derived from it —
 * see StockLevel's own docblock). Every stock-moving action — receipts,
 * adjustments, sales, ticket-line consumption, transfers — should call
 * this, never touch either table directly. Generalizes the pattern
 * `InventorySeeder::postReceipt()` established for the demo data.
 *
 * Callers must already be inside a transaction: this locks the stock_level
 * row (`SELECT ... FOR UPDATE`) before computing the new balance, the same
 * discipline as `Sequence::next()` and `RepairTicketService::transition()`.
 */
class StockMovementRecorder
{
    public function record(
        int $productId,
        int $branchId,
        float $quantity,
        float $unitCost,
        string $movementType,
        int $actorId,
        ?int $serializedUnitId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $reasonCode = null,
    ): StockMovement {
        $level = StockLevel::withoutGlobalScopes()
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->lockForUpdate()
            ->first();

        if ($level === null) {
            try {
                $level = StockLevel::create([
                    'product_id' => $productId,
                    'branch_id' => $branchId,
                    'on_hand_qty' => 0,
                    'reserved_qty' => 0,
                    'updated_at' => now(),
                ]);
            } catch (QueryException) {
                // Lost the race to create this row — another transaction
                // just committed it; re-select under the lock and continue.
                $level = StockLevel::withoutGlobalScopes()
                    ->where('product_id', $productId)
                    ->where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
        }

        $balanceAfter = round((float) $level->on_hand_qty + $quantity, 2);

        $movement = StockMovement::create([
            'product_id' => $productId,
            'branch_id' => $branchId,
            'serialized_unit_id' => $serializedUnitId,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'movement_type' => $movementType,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reason_code' => $reasonCode,
            'actor_id' => $actorId,
            'balance_after' => $balanceAfter,
            'occurred_at' => now(),
        ]);

        $level->on_hand_qty = $balanceAfter;
        $level->updated_at = now();
        $level->save();

        return $movement;
    }
}
