<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Refund;
use App\Models\RefundLine;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SerializedUnit;
use App\Models\User;
use App\Support\Api\ApiException;
use App\Support\Api\ErrorCode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lines are keyed by their position in `GET /sales/{sale}` (`sale.lines`),
 * not an internal sale_line id — sale_lines has no ulid of its own
 * (nested-only), so there's nothing else to reference one by without
 * breaking Rule 6, same reasoning as ReceivePurchaseOrderRequest.
 */
class RefundService
{
    public function __construct(private readonly StockMovementRecorder $movements) {}

    public function create(Sale $sale, array $data, User $actor): Refund
    {
        return DB::transaction(function () use ($sale, $data, $actor) {
            if (! in_array($sale->status, ['completed', 'partially_refunded'], true)) {
                throw new ApiException(ErrorCode::InvalidStatusTransition, "Only a completed sale can be refunded (currently {$sale->status}).");
            }

            $saleLines = $sale->lines()->orderBy('id')->get();

            $refund = Refund::create([
                'sale_id' => $sale->id,
                'reason_code' => $data['reason_code'],
                'processed_by' => $actor->id,
            ]);

            foreach ($data['lines'] as $lineData) {
                /** @var SaleLine $saleLine */
                $saleLine = $saleLines[$lineData['line_index']];
                $alreadyRefunded = (float) RefundLine::where('sale_line_id', $saleLine->id)->sum('quantity');
                $remaining = round((float) $saleLine->quantity - $alreadyRefunded, 2);

                if ((float) $lineData['quantity'] - $remaining > 0.01) {
                    throw new ApiException(ErrorCode::ValidationFailed, "Cannot refund more than the {$remaining} remaining on this line.");
                }

                $unitAmount = (float) $saleLine->amount / (float) $saleLine->quantity;
                $amount = round($unitAmount * (float) $lineData['quantity'], 2);

                RefundLine::create([
                    'refund_id' => $refund->id,
                    'sale_line_id' => $saleLine->id,
                    'quantity' => $lineData['quantity'],
                    'amount' => $amount,
                    'restock_behavior' => $lineData['restock_behavior'],
                ]);

                if ($lineData['restock_behavior'] === 'restock') {
                    $this->restock($saleLine, (float) $lineData['quantity'], $sale, $actor, $refund->id);
                }
            }

            $this->syncSaleStatus($sale, $saleLines);

            return $refund->fresh(['lines.saleLine', 'processor']);
        });
    }

    /**
     * 'no_restock' and 'write_off' both leave stock exactly as the
     * original sale left it — the unit already left inventory then; the
     * only question restock_behavior answers is whether it can go back.
     */
    private function restock(SaleLine $saleLine, float $quantity, Sale $sale, User $actor, int $refundId): void
    {
        if ($saleLine->sellable_type === 'product') {
            $product = Product::find($saleLine->sellable_id);
            if ($product?->track_inventory) {
                $this->movements->record(
                    productId: $product->id,
                    branchId: $sale->branch_id,
                    quantity: $quantity,
                    unitCost: (float) ($saleLine->unit_cost ?? 0),
                    movementType: 'return_in',
                    actorId: $actor->id,
                    referenceType: 'refund',
                    referenceId: $refundId,
                );
            }
        } elseif ($saleLine->sellable_type === 'serialized_unit') {
            $unit = SerializedUnit::find($saleLine->sellable_id);
            if ($unit !== null && $unit->status === 'sold') {
                $unit->transitionStatus('sold', 'in_stock');
                $this->movements->record(
                    productId: $unit->product_id,
                    branchId: $sale->branch_id,
                    quantity: 1,
                    unitCost: (float) ($saleLine->unit_cost ?? 0),
                    movementType: 'return_in',
                    actorId: $actor->id,
                    serializedUnitId: $unit->id,
                    referenceType: 'refund',
                    referenceId: $refundId,
                );
            }
        }
    }

    private function syncSaleStatus(Sale $sale, Collection $saleLines): void
    {
        $totalQty = (float) $saleLines->sum('quantity');
        $refundedQty = (float) RefundLine::whereIn('sale_line_id', $saleLines->pluck('id'))->sum('quantity');

        if ($refundedQty <= 0) {
            return;
        }

        $sale->update(['status' => $refundedQty >= $totalQty - 0.01 ? 'refunded' : 'partially_refunded']);
    }
}
