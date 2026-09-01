<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\RepairTicket;
use App\Models\Sale;
use App\Models\StockLevel;
use App\Support\BranchContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The landing-page summary, in two detail levels.
 *
 * `full` (needs reports.margin.view — owner/manager) carries money and
 * stock value; `limited` (any reports.view holder — the cashier) carries
 * only counts and today's takings, no cost, no margin, no valuation.
 *
 * Scope follows BranchContext exactly like every other read: the caller's
 * own branch unless an owner asked for ?branch=all / ?branch={ulid}. When
 * it does span branches, the response gains a per-branch `branches[]`
 * breakdown — that's the one dashboard the owner wanted, both branches'
 * sales, repairs, and inventory side by side.
 *
 * Computed live off the transactional tables for the same reason
 * ReportService is (the rollup tables exist but nothing populates them).
 */
class DashboardService
{
    /** Ticket statuses still occupying bench space / awaiting the customer. */
    private const OPEN_TICKET_STATUSES = [
        'received', 'diagnosed', 'awaiting_approval', 'awaiting_parts',
        'in_repair', 'qc', 'ready_for_pickup',
    ];

    public function __construct(private readonly BranchContext $context) {}

    /** @return array<string, mixed> */
    public function summary(bool $includeFinancials): array
    {
        $today = Carbon::today();

        $data = [
            'scope' => $this->context->spansAllBranches() ? 'all_branches' : 'branch',
            'as_of' => now()->toIso8601String(),
            'totals' => $this->metrics($today, $includeFinancials),
        ];

        // Only a cross-branch request gets the per-branch split; a
        // single-branch caller's `totals` already IS their branch.
        if ($this->context->spansAllBranches()) {
            $data['branches'] = $this->perBranch($today, $includeFinancials);
        }

        return $data;
    }

    /**
     * One branch's numbers, or every in-scope branch's when $branchId is
     * null. Relies on BranchScope for the in-scope case, and constrains
     * explicitly when drilling into a single branch for the breakdown.
     *
     * @return array<string, mixed>
     */
    private function metrics(Carbon $today, bool $includeFinancials, ?int $branchId = null): array
    {
        // Qualified, because the stock queries below join `products` and an
        // unqualified branch_id would be ambiguous there.
        $scoped = fn ($query) => $branchId === null
            ? $query
            : $query->where($query->getModel()->qualifyColumn('branch_id'), $branchId);

        $salesToday = $scoped(Sale::query())
            ->where('status', '!=', 'voided')
            ->whereDate('created_at', $today);

        $openTickets = $scoped(RepairTicket::query())
            ->whereIn('status', self::OPEN_TICKET_STATUSES);

        $metrics = [
            'sales' => [
                'count_today' => (clone $salesToday)->count(),
                'gross_today' => (string) round((float) (clone $salesToday)->sum('total'), 2),
            ],
            'repairs' => [
                'open' => (clone $openTickets)->count(),
                'ready_for_pickup' => $scoped(RepairTicket::query())->where('status', 'ready_for_pickup')->count(),
                'awaiting_approval' => $scoped(RepairTicket::query())->where('status', 'awaiting_approval')->count(),
                'unclaimed' => $scoped(RepairTicket::query())->where('status', 'unclaimed')->count(),
                'intake_today' => $scoped(RepairTicket::query())->whereDate('created_at', $today)->count(),
            ],
            'inventory' => [
                // reorder_point lives on products, not stock_levels.
                'low_stock_items' => $scoped(StockLevel::query())
                    ->join('products', 'products.id', '=', 'stock_levels.product_id')
                    ->whereColumn('stock_levels.on_hand_qty', '<=', 'products.reorder_point')
                    ->count(),
            ],
        ];

        // Cost and valuation never reach a limited caller — the cashier's
        // dashboard shows activity, not the shop's economics.
        if ($includeFinancials) {
            $metrics['inventory']['stock_value'] = (string) round(
                (float) $scoped(StockLevel::query())
                    ->join('products', 'products.id', '=', 'stock_levels.product_id')
                    ->sum(DB::raw('stock_levels.on_hand_qty * COALESCE(products.cost, 0)')),
                2,
            );
        }

        return $metrics;
    }

    /** @return array<int, array<string, mixed>> */
    private function perBranch(Carbon $today, bool $includeFinancials): array
    {
        return Branch::query()
            ->orderBy('id')
            ->get()
            ->map(fn (Branch $branch) => [
                'ulid' => $branch->ulid,
                'name' => $branch->name,
                'code' => $branch->code,
                'type' => $branch->type->value,
                'offers_repairs' => $branch->offersRepairs(),
                'metrics' => $this->metrics($today, $includeFinancials, $branch->id),
            ])
            ->all();
    }
}
