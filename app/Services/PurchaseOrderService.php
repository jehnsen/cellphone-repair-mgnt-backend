<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use App\Support\Api\ApiException;
use App\Support\Api\ErrorCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    private const TRANSITIONS = [
        'draft' => ['submitted', 'cancelled'],
        'submitted' => ['cancelled'],
    ];

    public function __construct(
        private readonly PurchaseOrderRepositoryInterface $orders,
        private readonly GoodsReceiptService $receipts,
    ) {}

    public function list(): LengthAwarePaginator
    {
        return $this->orders->paginate();
    }

    public function create(array $data, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $actor) {
            $po = $this->orders->create([
                'branch_id' => $data['branch_id'],
                'supplier_id' => $data['supplier_id'],
                'status' => 'draft',
                'expected_date' => $data['expected_date'] ?? null,
                'created_by' => $actor->id,
            ]);

            foreach ($data['lines'] as $line) {
                PurchaseOrderLine::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $line['product_id'],
                    'ordered_qty' => $line['ordered_qty'],
                    'unit_cost' => $line['unit_cost'],
                ]);
            }

            return $po->fresh(['lines.product', 'supplier']);
        });
    }

    /** Header edits only — status changes go through the fixed transition graph below. */
    public function update(PurchaseOrder $po, array $data): PurchaseOrder
    {
        if (isset($data['status']) && $data['status'] !== $po->status) {
            $this->assertLegalTransition($po->status, $data['status']);
        }

        return $this->orders->update($po, $data)->fresh(['lines.product', 'supplier']);
    }

    /**
     * Creates a goods receipt for however much of each line is being
     * received now (can be less than ordered_qty — a partial delivery) and
     * recomputes the PO's own status from every line's received_qty. Lines
     * are keyed by product_id (resolved from product_ulid in the
     * controller) rather than the internal purchase_order_line id — see
     * ReceivePurchaseOrderRequest's docblock.
     */
    public function receive(PurchaseOrder $po, array $data, User $actor): GoodsReceipt
    {
        return DB::transaction(function () use ($po, $data, $actor) {
            if (! in_array($po->status, ['submitted', 'partially_received'], true)) {
                throw new ApiException(ErrorCode::InvalidStatusTransition, "A purchase order must be submitted before it can be received (currently {$po->status}).");
            }

            $lines = array_map(function (array $line) use ($po) {
                /** @var PurchaseOrderLine $poLine */
                $poLine = PurchaseOrderLine::where('purchase_order_id', $po->id)
                    ->where('product_id', $line['product_id'])
                    ->firstOrFail();
                $remaining = round((float) $poLine->ordered_qty - (float) $poLine->received_qty, 2);

                if ((float) $line['quantity'] - $remaining > 0.01) {
                    throw new ApiException(ErrorCode::ValidationFailed, "Cannot receive more than the {$remaining} still outstanding on this line.");
                }

                return [
                    'product_id' => $poLine->product_id,
                    'quantity' => (float) $line['quantity'],
                    'unit_cost' => (float) $poLine->unit_cost,
                    'purchase_order_line_id' => $poLine->id,
                ];
            }, $data['lines']);

            $receipt = $this->receipts->post($po->branch_id, $po->id, $po->supplier_id, $lines, $actor);

            $this->syncStatus($po);

            return $receipt;
        });
    }

    private function syncStatus(PurchaseOrder $po): void
    {
        $lines = PurchaseOrderLine::where('purchase_order_id', $po->id)->get();
        $allReceived = $lines->every(fn (PurchaseOrderLine $l) => (float) $l->received_qty >= (float) $l->ordered_qty - 0.01);

        $po->update(['status' => $allReceived ? 'received' : 'partially_received']);
    }

    private function assertLegalTransition(string $from, string $to): void
    {
        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new ApiException(ErrorCode::InvalidStatusTransition, "Cannot change a purchase order from '{$from}' to '{$to}'.");
        }
    }
}
