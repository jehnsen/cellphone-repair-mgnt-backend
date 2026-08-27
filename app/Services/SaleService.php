<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Discount;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\Sequence;
use App\Models\SerializedUnit;
use App\Models\Service;
use App\Models\StockLevel;
use App\Models\User;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Repositories\Contracts\ShiftRepositoryInterface;
use App\Support\Api\ApiException;
use App\Support\Api\ErrorCode;
use App\Support\SaleCalculator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The POS checkout. See SaleCalculator for the VAT/discount math; this
 * class resolves each line's actual sellable, checks/consumes stock, and
 * assembles the Sale + SaleLine + Discount rows in one transaction.
 */
class SaleService
{
    public function __construct(
        private readonly SaleRepositoryInterface $sales,
        private readonly ShiftRepositoryInterface $shiftRepository,
        private readonly StockMovementRecorder $movements,
    ) {}

    public function list(): LengthAwarePaginator
    {
        return $this->sales->paginate();
    }

    public function create(array $data, User $actor): Sale
    {
        return DB::transaction(function () use ($data, $actor) {
            $shift = $this->shiftRepository->findOpenFor($actor);
            if ($shift === null) {
                throw new ApiException(ErrorCode::ShiftNotOpen, 'You need an open shift to record a sale.');
            }

            $branch = Branch::findOrFail($actor->branch_id);

            $resolved = array_map(
                fn (array $line) => $this->resolveLine($line, $branch->id),
                $data['lines'],
            );

            $saleDiscount = $data['sale_discount'] ?? null;
            if ($saleDiscount !== null && ! isset($saleDiscount['value'])) {
                // senior_citizen/pwd is a legally-fixed rate — the client
                // doesn't have to supply it (same default the discount
                // preview endpoint uses).
                $saleDiscount['value'] = SaleCalculator::SENIOR_PWD_RATE * 100;
            }

            $totals = SaleCalculator::compute(
                array_map(fn ($r) => ['amount' => $r['gross'], 'discount' => $r['discount']], $resolved),
                $saleDiscount,
                (bool) $branch->vat_registered,
            );

            $sale = Sale::create([
                'branch_id' => $branch->id,
                'sale_number' => $this->generateSaleNumber($branch, now()),
                'customer_id' => $data['customer_id'] ?? null,
                'cashier_id' => $actor->id,
                'shift_id' => $shift->id,
                ...$totals,
                'status' => 'completed',
                'source' => 'pos',
                'client_uuid' => $data['client_uuid'] ?? null,
            ]);

            foreach ($resolved as $r) {
                $net = SaleCalculator::applyPercentOrAmount($r['gross'], $r['discount']);

                $line = SaleLine::create([
                    'sale_id' => $sale->id,
                    'sellable_type' => $r['sellable_type'],
                    'sellable_id' => $r['sellable_id'],
                    'quantity' => $r['quantity'],
                    'unit_price' => $r['unit_price'],
                    'unit_cost' => $r['unit_cost'],
                    'line_discount' => round($r['gross'] - $net, 2),
                    'amount' => round($net, 2),
                ]);

                if ($r['discount'] !== null) {
                    Discount::create([
                        'sale_id' => $sale->id,
                        'sale_line_id' => $line->id,
                        'type' => $r['discount']['type'],
                        'value' => $r['discount']['value'],
                        'scope' => 'line',
                    ]);
                }

                $this->consumeStock($r, $sale, $actor);
            }

            if ($saleDiscount !== null) {
                $sd = $saleDiscount;
                Discount::create([
                    'sale_id' => $sale->id,
                    'sale_line_id' => null,
                    'type' => $sd['type'],
                    'value' => $sd['value'],
                    'scope' => 'sale',
                    'id_type' => $sd['id_type'] ?? null,
                    'id_number' => $sd['id_number'] ?? null,
                    'cardholder_name' => $sd['cardholder_name'] ?? null,
                ]);
            }

            return $sale->fresh(['lines', 'discounts', 'customer']);
        });
    }

    public function show(Sale $sale): Sale
    {
        return $sale->load([
            'lines',
            'discounts',
            'customer',
            'cashier' => fn ($query) => $query->withoutGlobalScopes(),
        ]);
    }

    /**
     * Reverses stock consumption (return_in) and flips any serialized unit
     * back to in_stock — a void undoes the sale's physical effects, not
     * just its bookkeeping status. Refunds (partial, with restocking
     * choices per line) are a separate, not-yet-built action — see README.
     */
    public function void(Sale $sale, array $data, User $actor): Sale
    {
        return DB::transaction(function () use ($sale, $data, $actor) {
            if ($sale->status !== 'completed') {
                throw new ApiException(ErrorCode::InvalidStatusTransition, 'Only a completed sale can be voided.');
            }

            foreach ($sale->lines as $line) {
                if ($line->sellable_type === 'product') {
                    $product = Product::find($line->sellable_id);
                    if ($product?->track_inventory) {
                        $this->movements->record(
                            productId: $product->id,
                            branchId: $sale->branch_id,
                            quantity: (float) $line->quantity,
                            unitCost: (float) ($line->unit_cost ?? 0),
                            movementType: 'return_in',
                            actorId: $actor->id,
                            referenceType: 'sale_void',
                            referenceId: $sale->id,
                        );
                    }
                } elseif ($line->sellable_type === 'serialized_unit') {
                    $unit = SerializedUnit::find($line->sellable_id);
                    if ($unit !== null && $unit->status === 'sold') {
                        $unit->transitionStatus('sold', 'in_stock');
                        $this->movements->record(
                            productId: $unit->product_id,
                            branchId: $sale->branch_id,
                            quantity: 1,
                            unitCost: (float) ($line->unit_cost ?? 0),
                            movementType: 'return_in',
                            actorId: $actor->id,
                            serializedUnitId: $unit->id,
                            referenceType: 'sale_void',
                            referenceId: $sale->id,
                        );
                    }
                }
            }

            $sale->update(['status' => 'voided', 'void_reason' => $data['void_reason']]);

            return $sale->fresh(['lines', 'discounts']);
        });
    }

    /** @return array{sellable_type: string, sellable_id: ?int, quantity: float, unit_price: float, unit_cost: ?float, gross: float, discount: ?array, product_id: ?int} */
    private function resolveLine(array $line, int $branchId): array
    {
        $discount = $line['discount'] ?? null;

        return match ($line['sellable_type']) {
            'product' => $this->resolveProductLine($line, $branchId, $discount),
            'serialized_unit' => $this->resolveSerializedUnitLine($line, $discount),
            'service' => $this->resolveServiceLine($line, $discount),
        };
    }

    private function resolveProductLine(array $line, int $branchId, ?array $discount): array
    {
        $product = Product::findOrFail($line['product_id']);
        $quantity = (float) $line['quantity'];

        if ($product->track_inventory) {
            $level = StockLevel::withoutGlobalScopes()
                ->where('product_id', $product->id)
                ->where('branch_id', $branchId)
                ->first();
            $available = $level ? (float) $level->on_hand_qty - (float) $level->reserved_qty : 0.0;

            if ($available < $quantity) {
                throw new ApiException(ErrorCode::InsufficientStock, "Only {$available} of \"{$product->name}\" available.");
            }
        }

        return [
            'sellable_type' => 'product',
            'sellable_id' => $product->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => (float) $product->selling_price,
            'unit_cost' => (float) $product->cost,
            'gross' => round($quantity * (float) $product->selling_price, 2),
            'discount' => $discount,
        ];
    }

    private function resolveSerializedUnitLine(array $line, ?array $discount): array
    {
        /** @var SerializedUnit $unit */
        $unit = SerializedUnit::with('product')->findOrFail($line['serialized_unit_id']);
        $unit->transitionStatus('in_stock', 'sold');

        return [
            'sellable_type' => 'serialized_unit',
            'sellable_id' => $unit->id,
            'product_id' => $unit->product_id,
            'quantity' => 1,
            'unit_price' => (float) $unit->product->selling_price,
            'unit_cost' => (float) $unit->acquisition_cost,
            'gross' => round((float) $unit->product->selling_price, 2),
            'discount' => $discount,
        ];
    }

    private function resolveServiceLine(array $line, ?array $discount): array
    {
        $service = Service::findOrFail($line['service_id']);
        $quantity = (float) ($line['quantity'] ?? 1);

        return [
            'sellable_type' => 'service',
            'sellable_id' => $service->id,
            'product_id' => null,
            'quantity' => $quantity,
            'unit_price' => (float) $service->default_price,
            'unit_cost' => null,
            'gross' => round($quantity * (float) $service->default_price, 2),
            'discount' => $discount,
        ];
    }

    private function consumeStock(array $resolved, Sale $sale, User $actor): void
    {
        if ($resolved['sellable_type'] === 'product') {
            $product = Product::find($resolved['product_id']);
            if ($product?->track_inventory) {
                $this->movements->record(
                    productId: $resolved['product_id'],
                    branchId: $sale->branch_id,
                    quantity: -$resolved['quantity'],
                    unitCost: (float) $resolved['unit_cost'],
                    movementType: 'sale',
                    actorId: $actor->id,
                    referenceType: 'sale',
                    referenceId: $sale->id,
                );
            }

            return;
        }

        if ($resolved['sellable_type'] === 'serialized_unit') {
            $this->movements->record(
                productId: $resolved['product_id'],
                branchId: $sale->branch_id,
                quantity: -1,
                unitCost: (float) $resolved['unit_cost'],
                movementType: 'sale',
                actorId: $actor->id,
                serializedUnitId: $resolved['sellable_id'],
                referenceType: 'sale',
                referenceId: $sale->id,
            );
        }
    }

    private function generateSaleNumber(Branch $branch, Carbon $at): string
    {
        $n = Sequence::next($branch->id, 'sale', (int) $at->format('Y'), (int) $at->format('m'));

        return sprintf('SI-%s-%s-%04d', $branch->code, $at->format('Ym'), $n);
    }
}
