<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleWarranty;
use App\Models\Sequence;
use App\Models\SerializedUnit;
use App\Models\User;
use App\Repositories\Contracts\SaleWarrantyRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The shop-issued warranty a serialized unit leaves with when it's sold.
 * Kept apart from the repair-ticket `Warranty` on purpose: a customer
 * availing this never lands on the repair board unless someone explicitly
 * links a job order to the claim.
 *
 * `issueForLine()` is the write path — called by SaleService as each
 * serialized-unit line is committed, inside that sale's transaction.
 */
class SaleWarrantyService
{
    public function __construct(private readonly SaleWarrantyRepositoryInterface $warranties) {}

    public function list(): LengthAwarePaginator
    {
        return $this->warranties->paginate();
    }

    public function show(SaleWarranty $warranty): SaleWarranty
    {
        return $warranty->load([
            'serializedUnit.product',
            'sale',
            'customer' => fn ($query) => $query->withoutGlobalScopes(),
            'claims',
        ]);
    }

    /** @return Collection<int, SaleWarranty> */
    public function forSale(Sale $sale): Collection
    {
        return SaleWarranty::query()
            ->where('sale_id', $sale->id)
            ->with(['serializedUnit.product', 'claims'])
            ->orderByDesc('id')
            ->get();
    }

    /** @return Collection<int, SaleWarranty> */
    public function forUnit(SerializedUnit $unit): Collection
    {
        return SaleWarranty::query()
            ->where('serialized_unit_id', $unit->id)
            ->with(['sale', 'claims'])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Issue the warranty for one just-written serialized-unit sale line.
     * Returns null (issues nothing) when the effective term is zero — the
     * unit's product carries no catalog warranty and the cashier entered
     * none. Per the client's rule, the clock starts on the sale date.
     */
    public function issueForLine(Sale $sale, SaleLine $line, array $resolved, User $actor): ?SaleWarranty
    {
        $product = Product::find($resolved['product_id']);

        $termDays = $resolved['warranty_days'] ?? $product?->warranty_days ?? 0;
        $termDays = (int) $termDays;

        if ($termDays <= 0) {
            return null;
        }

        $startsAt = ($sale->created_at ?? Carbon::now())->copy()->startOfDay();

        return $this->warranties->create([
            'branch_id' => $sale->branch_id,
            'sale_id' => $sale->id,
            'sale_line_id' => $line->id,
            'serialized_unit_id' => $resolved['sellable_id'],
            'customer_id' => $sale->customer_id,
            'coverage' => $resolved['warranty_coverage'] ?? 'shop',
            'term_days' => $termDays,
            'starts_at' => $startsAt->toDateString(),
            'expiry_date' => $startsAt->copy()->addDays($termDays)->toDateString(),
            'warranty_code' => $this->generateCode($sale->branch_id, $startsAt),
            'terms' => $resolved['warranty_terms'] ?? null,
        ]);
    }

    /** A voided/refunded sale takes its issued warranties down with it. */
    public function voidForSale(Sale $sale): void
    {
        SaleWarranty::query()
            ->where('sale_id', $sale->id)
            ->whereNull('voided_at')
            ->update(['voided_at' => now()]);
    }

    private function generateCode(int $branchId, Carbon $at): string
    {
        $branch = Branch::find($branchId);
        $n = Sequence::next($branchId, 'sale_warranty', (int) $at->format('Y'), (int) $at->format('m'));

        return sprintf('SW-%s-%s-%04d', $branch->code, $at->format('Ym'), $n);
    }
}
