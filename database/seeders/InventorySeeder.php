<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\Product;
use App\Models\SerializedUnit;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 40 serialized handset units plus a realistic opening stock position for
 * every non-serialized product, each backed by a goods receipt so the
 * stock_movements ledger and the stock_levels cache agree from day one —
 * the same invariant Stage 7's reconcile-check command will later verify.
 */
class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::all();
        $supplier = Supplier::query()->firstOrFail();
        $receiver = User::role('manager')->first() ?? User::query()->firstOrFail();

        $handsets = Product::where('type', 'handset')->get();
        $nonSerialized = Product::where('type', '!=', 'handset')->get();

        // 40 serialized units spread across the 15 handset SKUs and 2 branches.
        $unitsCreated = 0;
        while ($unitsCreated < 40) {
            $product = $handsets->random();
            $branch = $branches->random();

            DB::transaction(function () use ($product, $branch, $supplier, $receiver): void {
                $unit = SerializedUnit::factory()->create([
                    'product_id' => $product->id,
                    'branch_id' => $branch->id,
                    'condition' => fake()->randomElement(['brand_new', 'brand_new', 'brand_new', 'open_box', 'secondhand']),
                ]);

                $receipt = GoodsReceipt::factory()->create([
                    'branch_id' => $branch->id,
                    'supplier_id' => $supplier->id,
                    'received_by' => $receiver->id,
                ]);

                GoodsReceiptLine::factory()->create([
                    'goods_receipt_id' => $receipt->id,
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_cost' => $unit->acquisition_cost,
                    'serialized_unit_id' => $unit->id,
                ]);

                $this->postReceipt($product->id, $branch->id, 1, $unit->acquisition_cost, $receiver->id, $unit->id, $receipt->id);
            });

            $unitsCreated++;
        }

        // Opening stock for every accessory/part, at each branch.
        foreach ($nonSerialized as $product) {
            foreach ($branches as $branch) {
                DB::transaction(function () use ($product, $branch, $supplier, $receiver): void {
                    $qty = random_int(5, 60);

                    $receipt = GoodsReceipt::factory()->create([
                        'branch_id' => $branch->id,
                        'supplier_id' => $supplier->id,
                        'received_by' => $receiver->id,
                    ]);

                    GoodsReceiptLine::factory()->create([
                        'goods_receipt_id' => $receipt->id,
                        'product_id' => $product->id,
                        'quantity' => $qty,
                        'unit_cost' => $product->cost,
                        'serialized_unit_id' => null,
                    ]);

                    $this->postReceipt($product->id, $branch->id, $qty, $product->cost, $receiver->id, null, $receipt->id);
                });
            }
        }
    }

    private function postReceipt(
        int $productId,
        int $branchId,
        float $qty,
        float $unitCost,
        int $actorId,
        ?int $serializedUnitId,
        int $goodsReceiptId,
    ): void {
        $level = StockLevel::query()
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->lockForUpdate()
            ->first();

        if ($level === null) {
            $level = StockLevel::create([
                'product_id' => $productId,
                'branch_id' => $branchId,
                'on_hand_qty' => 0,
                'reserved_qty' => 0,
                'updated_at' => now(),
            ]);
        }

        $balanceAfter = (float) $level->on_hand_qty + $qty;

        StockMovement::create([
            'product_id' => $productId,
            'branch_id' => $branchId,
            'serialized_unit_id' => $serializedUnitId,
            'quantity' => $qty,
            'unit_cost' => $unitCost,
            'movement_type' => 'receipt',
            'reference_type' => 'goods_receipt',
            'reference_id' => $goodsReceiptId,
            'actor_id' => $actorId,
            'balance_after' => $balanceAfter,
            'occurred_at' => now(),
        ]);

        $level->on_hand_qty = $balanceAfter;
        $level->updated_at = now();
        $level->save();
    }
}
