<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Repositories\Contracts\GoodsReceiptRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * The one place that actually posts a receipt into inventory — called
 * directly for an ad-hoc receipt (no PO) and by
 * PurchaseOrderService::receive() for a PO-backed one. Serialized units
 * (handsets) aren't created here; register them via
 * POST /serialized-units instead (Stage 6) once they're physically in.
 */
class GoodsReceiptService
{
    public function __construct(
        private readonly GoodsReceiptRepositoryInterface $receipts,
        private readonly StockMovementRecorder $movements,
    ) {}

    public function list(): LengthAwarePaginator
    {
        return $this->receipts->paginate();
    }

    /** @param  list<array{product_id:int, quantity:float, unit_cost:float, purchase_order_line_id?:int}>  $lines */
    public function post(int $branchId, ?int $purchaseOrderId, int $supplierId, array $lines, User $actor): GoodsReceipt
    {
        return DB::transaction(function () use ($branchId, $purchaseOrderId, $supplierId, $lines, $actor) {
            $receipt = GoodsReceipt::create([
                'branch_id' => $branchId,
                'purchase_order_id' => $purchaseOrderId,
                'supplier_id' => $supplierId,
                'status' => 'posted',
                'received_by' => $actor->id,
                'received_at' => now(),
            ]);

            foreach ($lines as $line) {
                GoodsReceiptLine::create([
                    'goods_receipt_id' => $receipt->id,
                    'purchase_order_line_id' => $line['purchase_order_line_id'] ?? null,
                    'product_id' => $line['product_id'],
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                ]);

                if (isset($line['purchase_order_line_id'])) {
                    PurchaseOrderLine::whereKey($line['purchase_order_line_id'])->increment('received_qty', $line['quantity']);
                }

                $this->movements->record(
                    productId: $line['product_id'],
                    branchId: $branchId,
                    quantity: (float) $line['quantity'],
                    unitCost: (float) $line['unit_cost'],
                    movementType: 'receipt',
                    actorId: $actor->id,
                    referenceType: 'goods_receipt',
                    referenceId: $receipt->id,
                );
            }

            return $receipt->fresh(['lines.product', 'supplier']);
        });
    }
}
