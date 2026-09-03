<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\CommissionEntry;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\RepairTicket;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\Shift;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\TicketEvent;
use App\Models\TicketLine;
use App\Models\User;
use App\Models\WarrantyClaim;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only, all scoped by the caller's own branch (BranchScope on the
 * underlying models). The design brief's own rule says reporting should
 * read from rollup tables (daily_metrics etc.), never scan transactional
 * tables per request — those rollup tables exist (Stage 3) but nothing
 * populates them yet (that's its own undertaking: a scheduled command).
 * These compute live instead, which is fine at this shop's data volume;
 * switching the read side to the rollups later shouldn't change any of
 * these method signatures.
 */
class ReportService
{
    public function sales(?string $from, ?string $to): array
    {
        $query = Sale::where('status', '!=', 'voided')->whereBetween('created_at', $this->range($from, $to));

        return [
            'aggregate' => [
                'gross_sales' => (string) round((float) $query->clone()->sum('total'), 2),
                'discount_total' => (string) round((float) $query->clone()->sum('discount_total'), 2),
                'vat_total' => (string) round((float) $query->clone()->sum('vat_amount'), 2),
                'sale_count' => $query->clone()->count(),
            ],
            'rows' => $query->clone()->selectRaw('DATE(created_at) as business_date, COUNT(*) as sale_count, SUM(total) as gross_sales')
                ->groupBy('business_date')->orderBy('business_date')->get(),
        ];
    }

    public function margin(?string $from, ?string $to): array
    {
        $lines = SaleLine::whereHas('sale', fn ($q) => $q->where('status', '!=', 'voided')->whereBetween('created_at', $this->range($from, $to)));

        $revenue = (float) $lines->clone()->sum('amount');
        $cogs = (float) $lines->clone()->selectRaw('SUM(quantity * COALESCE(unit_cost, 0)) as v')->value('v');

        return [
            'aggregate' => [
                'revenue' => (string) round($revenue, 2),
                'cogs' => (string) round($cogs, 2),
                'gross_margin' => (string) round($revenue - $cogs, 2),
            ],
        ];
    }

    public function technicianThroughput(?string $from, ?string $to): array
    {
        $range = $this->range($from, $to);

        $received = RepairTicket::whereBetween('created_at', $range)
            ->whereNotNull('assigned_technician_id')
            ->selectRaw('assigned_technician_id, COUNT(*) as tickets_received')
            ->groupBy('assigned_technician_id')
            ->get()
            ->keyBy('assigned_technician_id');

        $released = TicketEvent::where('event_type', 'status_changed')->where('to_status', 'released')
            ->whereBetween('ticket_events.created_at', $range)
            ->join('repair_tickets', 'repair_tickets.id', '=', 'ticket_events.repair_ticket_id')
            ->whereNotNull('repair_tickets.assigned_technician_id')
            ->selectRaw('repair_tickets.assigned_technician_id, COUNT(*) as tickets_released')
            ->groupBy('repair_tickets.assigned_technician_id')
            ->get()
            ->keyBy('assigned_technician_id');

        $technicianIds = $received->keys()->merge($released->keys())->unique();

        return [
            'rows' => $technicianIds->map(fn ($id) => [
                'technician_id' => (int) $id,
                'technician' => User::withoutGlobalScopes()->find($id)?->name,
                'tickets_received' => (int) ($received[$id]->tickets_received ?? 0),
                'tickets_released' => (int) ($released[$id]->tickets_released ?? 0),
            ])->values(),
        ];
    }

    public function mostRepairedModels(?string $from, ?string $to): array
    {
        $rows = RepairTicket::whereBetween('created_at', $this->range($from, $to))
            ->selectRaw('device_brand_snapshot, device_model_snapshot, COUNT(*) as ticket_count')
            ->groupBy('device_brand_snapshot', 'device_model_snapshot')
            ->orderByDesc('ticket_count')
            ->limit(20)
            ->get();

        return ['rows' => $rows];
    }

    public function warrantyFailureRate(): array
    {
        $claims = (int) WarrantyClaim::query()->count();

        return [
            'aggregate' => [
                'warranty_claims' => $claims,
                'note' => 'Populated once warranty issuance (Stage 5 deferred item) and claims are wired up.',
            ],
            'rows' => WarrantyClaim::selectRaw('product_id, COUNT(*) as claim_count')
                ->whereNotNull('product_id')
                ->groupBy('product_id')
                ->with('product:id,ulid,name')
                ->get(),
        ];
    }

    public function inventoryValuation(): array
    {
        $rows = StockLevel::with('product')
            ->get()
            ->map(fn (StockLevel $level) => [
                'product' => $level->product?->name,
                'on_hand_qty' => (string) $level->on_hand_qty,
                'cost_value' => (string) round((float) $level->on_hand_qty * (float) ($level->product?->cost ?? 0), 2),
                'retail_value' => (string) round((float) $level->on_hand_qty * (float) ($level->product?->selling_price ?? 0), 2),
            ]);

        return [
            'aggregate' => [
                'total_cost_value' => (string) round((float) $rows->sum(fn ($r) => (float) $r['cost_value']), 2),
                'total_retail_value' => (string) round((float) $rows->sum(fn ($r) => (float) $r['retail_value']), 2),
                'sku_count' => $rows->count(),
            ],
            'rows' => $rows->values(),
        ];
    }

    /** Products with stock on hand but no outbound movement (sale/ticket_consumption) in the window. */
    public function deadStock(int $days): array
    {
        $since = now()->subDays($days);

        $movedProductIds = StockMovement::whereIn('movement_type', ['sale', 'ticket_consumption'])
            ->where('occurred_at', '>=', $since)
            ->distinct()
            ->pluck('product_id');

        $rows = StockLevel::with('product')
            ->where('on_hand_qty', '>', 0)
            ->whereNotIn('product_id', $movedProductIds)
            ->get()
            ->map(fn (StockLevel $level) => [
                'product' => $level->product?->name,
                'on_hand_qty' => (string) $level->on_hand_qty,
                'days_checked' => $days,
            ]);

        return ['rows' => $rows->values()];
    }

    public function unclaimedAging(): array
    {
        $rows = RepairTicket::where('status', 'unclaimed')
            ->get(['ulid', 'ticket_number', 'promised_date', 'updated_at'])
            ->map(fn (RepairTicket $ticket) => [
                'ticket_number' => $ticket->ticket_number,
                'ulid' => $ticket->ulid,
                'days_unclaimed' => (int) Carbon::parse($ticket->updated_at)->diffInDays(now()),
            ])
            ->sortByDesc('days_unclaimed')
            ->values();

        return ['rows' => $rows];
    }

    public function commissionsPayable(?string $from, ?string $to): array
    {
        $query = CommissionEntry::where('status', 'payable')->whereBetween('created_at', $this->range($from, $to));

        $rows = $query->clone()
            ->selectRaw('technician_id, SUM(amount) as total_payable, COUNT(*) as entry_count')
            ->groupBy('technician_id')
            ->get()
            ->map(fn ($row) => [
                'technician' => User::withoutGlobalScopes()->find($row->technician_id)?->name,
                'total_payable' => (string) round((float) $row->total_payable, 2),
                'entry_count' => (int) $row->entry_count,
            ]);

        return [
            'aggregate' => ['total_payable' => (string) round((float) $query->clone()->sum('amount'), 2)],
            'rows' => $rows,
        ];
    }

    /**
     * Repair P&L. `/reports/margin` only ever saw `sale_lines` — a repair
     * ticket's balance is paid directly (payable_type=repair_ticket) and
     * never becomes a Sale, so every peso of repair labour, repair-parts
     * revenue, and repair-parts COGS was invisible in every other report.
     *
     * Revenue is recognised at *release* — a ticket counts here once it has
     * a status_changed→released event inside the window, the point the job
     * is done and the money is earned. `payments_collected` is the cash
     * actually taken on those same tickets (any method), so the owner sees
     * earned-vs-collected side by side. Labour lines carry no cost, so
     * labour margin is simply labour revenue.
     */
    public function repairPnl(?string $from, ?string $to): array
    {
        $range = $this->range($from, $to);

        // Branch scope rides in via the RepairTicket subquery — ticket_events
        // itself is not branch-scoped.
        $releasedTicketIds = TicketEvent::query()
            ->where('event_type', 'status_changed')
            ->where('to_status', 'released')
            ->whereBetween('created_at', $range)
            ->whereIn('repair_ticket_id', RepairTicket::query()->select('id'))
            ->distinct()
            ->pluck('repair_ticket_id');

        $lines = TicketLine::query()->whereIn('repair_ticket_id', $releasedTicketIds);

        $partsRevenue = (float) $lines->clone()->where('line_type', 'part')->sum('amount');
        $laborRevenue = (float) $lines->clone()->where('line_type', 'labor')->sum('amount');
        $partsCost = (float) $lines->clone()->where('line_type', 'part')
            ->selectRaw('SUM(quantity * COALESCE(unit_cost, 0)) as v')->value('v');

        $totalRevenue = round($partsRevenue + $laborRevenue, 2);
        $grossMargin = round($totalRevenue - $partsCost, 2);

        $collected = (float) Payment::query()
            ->where('payable_type', 'repair_ticket')
            ->whereIn('payable_id', $releasedTicketIds)
            ->sum('amount');

        $rows = TicketLine::query()
            ->join('repair_tickets', 'repair_tickets.id', '=', 'ticket_lines.repair_ticket_id')
            ->whereIn('ticket_lines.repair_ticket_id', $releasedTicketIds)
            ->selectRaw(implode(' ', [
                'repair_tickets.assigned_technician_id as technician_id,',
                'COUNT(DISTINCT ticket_lines.repair_ticket_id) as tickets,',
                "SUM(CASE WHEN ticket_lines.line_type = 'part' THEN ticket_lines.amount ELSE 0 END) as parts_revenue,",
                "SUM(CASE WHEN ticket_lines.line_type = 'labor' THEN ticket_lines.amount ELSE 0 END) as labor_revenue,",
                "SUM(CASE WHEN ticket_lines.line_type = 'part' THEN ticket_lines.quantity * COALESCE(ticket_lines.unit_cost, 0) ELSE 0 END) as parts_cost",
            ]))
            ->groupBy('repair_tickets.assigned_technician_id')
            ->get()
            ->map(function ($r) {
                $revenue = (float) $r->parts_revenue + (float) $r->labor_revenue;

                return [
                    'technician_id' => $r->technician_id ? (int) $r->technician_id : null,
                    'technician' => $r->technician_id
                        ? User::withoutGlobalScopes()->find($r->technician_id)?->name
                        : null,
                    'tickets_released' => (int) $r->tickets,
                    'parts_revenue' => $this->money($r->parts_revenue),
                    'labor_revenue' => $this->money($r->labor_revenue),
                    'parts_cost' => $this->money($r->parts_cost),
                    'gross_margin' => $this->money($revenue - (float) $r->parts_cost),
                ];
            })
            ->values();

        return [
            'aggregate' => [
                'tickets_released' => $releasedTicketIds->count(),
                'parts_revenue' => $this->money($partsRevenue),
                'labor_revenue' => $this->money($laborRevenue),
                'total_revenue' => $this->money($totalRevenue),
                'parts_cost' => $this->money($partsCost),
                'parts_margin' => $this->money($partsRevenue - $partsCost),
                'labor_margin' => $this->money($laborRevenue),
                'gross_margin' => $this->money($grossMargin),
                'gross_margin_pct' => $totalRevenue > 0
                    ? $this->money($grossMargin / $totalRevenue * 100)
                    : '0.00',
                'payments_collected' => $this->money($collected),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * End-of-day cash reconciliation (Z-report). One row per shift whose
     * `opened_at` falls in the window, with the same expected-cash formula
     * ShiftService::close() uses — opening float + cash payments + cash in −
     * cash out (cash refunds already land as a cash-out movement, so they're
     * not subtracted again). An open shift still gets a live `expected_cash`;
     * `counted_cash`/`variance` stay null until it's closed. `tender_breakdown`
     * is every method that touched the shift, cash and non-cash alike.
     */
    public function cashReconciliation(?string $from, ?string $to): array
    {
        $shifts = Shift::query()
            ->with(['cashier' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'name')])
            ->whereBetween('opened_at', $this->range($from, $to))
            ->orderBy('opened_at')
            ->get();

        $rows = $shifts->map(function (Shift $shift) {
            $cashPayments = (float) Payment::where('shift_id', $shift->id)->where('method', 'cash')->sum('amount');
            $cashIn = (float) CashMovement::where('shift_id', $shift->id)->where('direction', 'in')->sum('amount');
            $cashOut = (float) CashMovement::where('shift_id', $shift->id)->where('direction', 'out')->sum('amount');

            $tender = Payment::where('shift_id', $shift->id)
                ->selectRaw('method, SUM(amount) as total')
                ->groupBy('method')
                ->pluck('total', 'method');

            $expected = round((float) $shift->opening_float + $cashPayments + $cashIn - $cashOut, 2);
            $counted = $shift->closed_at !== null ? (float) $shift->counted_cash : null;

            return [
                'shift_ulid' => $shift->ulid,
                'cashier' => $shift->cashier?->name,
                'opened_at' => $shift->opened_at?->toIso8601String(),
                'closed_at' => $shift->closed_at?->toIso8601String(),
                'status' => $shift->closed_at !== null ? 'closed' : 'open',
                'opening_float' => $this->money($shift->opening_float),
                'cash_payments' => $this->money($cashPayments),
                'cash_in' => $this->money($cashIn),
                'cash_out' => $this->money($cashOut),
                'expected_cash' => $this->money($expected),
                'counted_cash' => $counted === null ? null : $this->money($counted),
                'variance' => $counted === null ? null : $this->money($counted - $expected),
                'tender_breakdown' => $this->zeroedTenders($tender),
            ];
        });

        $tenderTotals = [];
        foreach (Payment::METHODS as $method) {
            $tenderTotals[$method] = $this->money(
                $rows->sum(fn ($r) => (float) $r['tender_breakdown'][$method]),
            );
        }

        return [
            'aggregate' => [
                'shift_count' => $rows->count(),
                'open_shift_count' => $rows->where('status', 'open')->count(),
                'closed_shift_count' => $rows->where('status', 'closed')->count(),
                'opening_float_total' => $this->money($rows->sum(fn ($r) => (float) $r['opening_float'])),
                'cash_payments_total' => $this->money($rows->sum(fn ($r) => (float) $r['cash_payments'])),
                'cash_in_total' => $this->money($rows->sum(fn ($r) => (float) $r['cash_in'])),
                'cash_out_total' => $this->money($rows->sum(fn ($r) => (float) $r['cash_out'])),
                'expected_cash_total' => $this->money($rows->sum(fn ($r) => (float) $r['expected_cash'])),
                'counted_cash_total' => $this->money(
                    $rows->whereNotNull('counted_cash')->sum(fn ($r) => (float) $r['counted_cash']),
                ),
                'variance_total' => $this->money(
                    $rows->whereNotNull('variance')->sum(fn ($r) => (float) $r['variance']),
                ),
                'tender_totals' => $tenderTotals,
            ],
            'rows' => $rows->values(),
        ];
    }

    /**
     * Refunds and voids in the window — the primary leakage/fraud signal in
     * retail, and something `/reports/sales` actively hides (it filters
     * `status != voided` and never looks at the refunds table). Refunds are
     * dated by `refunds.created_at`; a void has no timestamp of its own, so
     * it's dated by the sale's `updated_at` (the void is its last mutation).
     * Both lists are branch-scoped through the sale.
     */
    public function refundsVoids(?string $from, ?string $to): array
    {
        $range = $this->range($from, $to);

        // whereHas('sale') carries the Sale branch scope onto refunds, which
        // have no branch_id of their own.
        $refunds = Refund::query()
            ->whereHas('sale')
            ->whereBetween('created_at', $range)
            ->with([
                'sale:id,ulid,sale_number',
                'processor' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'name'),
            ])
            ->orderByDesc('created_at')
            ->get();

        $voids = Sale::query()
            ->where('status', 'voided')
            ->whereBetween('updated_at', $range)
            ->with(['cashier' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'name')])
            ->orderByDesc('updated_at')
            ->get(['id', 'ulid', 'sale_number', 'total', 'void_reason', 'cashier_id', 'updated_at']);

        $refundByMethod = [];
        foreach (Refund::METHODS as $method) {
            $subset = $refunds->where('refund_method', $method);

            if ($subset->isNotEmpty()) {
                $refundByMethod[$method] = [
                    'count' => $subset->count(),
                    'amount' => $this->money($subset->sum(fn ($r) => (float) $r->total_amount)),
                ];
            }
        }

        $refundByReason = $refunds->groupBy('reason_code')
            ->map(fn (Collection $group, $code) => [
                'reason_code' => (string) $code,
                'count' => $group->count(),
                'amount' => $this->money($group->sum(fn ($r) => (float) $r->total_amount)),
            ])
            ->values();

        return [
            'aggregate' => [
                'refund_count' => $refunds->count(),
                'refund_total' => $this->money($refunds->sum(fn ($r) => (float) $r->total_amount)),
                'refund_by_method' => $refundByMethod,
                'refund_by_reason' => $refundByReason,
                'void_count' => $voids->count(),
                'void_total' => $this->money($voids->sum(fn ($s) => (float) $s->total)),
            ],
            'rows' => [
                'refunds' => $refunds->map(fn (Refund $r) => [
                    'refund_ulid' => $r->ulid,
                    'sale_number' => $r->sale?->sale_number,
                    'sale_ulid' => $r->sale?->ulid,
                    'reason_code' => $r->reason_code,
                    'refund_method' => $r->refund_method,
                    'total_amount' => $this->money($r->total_amount),
                    'processed_by' => $r->processor?->name,
                    'processed_at' => $r->created_at?->toIso8601String(),
                ])->values(),
                'voids' => $voids->map(fn (Sale $s) => [
                    'sale_number' => $s->sale_number,
                    'sale_ulid' => $s->ulid,
                    'total' => $this->money($s->total),
                    'void_reason' => $s->void_reason,
                    'cashier' => $s->cashier?->name,
                    'voided_at' => $s->updated_at?->toIso8601String(),
                ])->values(),
            ],
        ];
    }

    /**
     * Outstanding repair balances, aged. A snapshot as of now (no window) —
     * every ticket still carrying `balance > 0`, bucketed by how long the
     * money has been owed. The clock starts at release when the ticket has
     * a release event, else its promised date, else intake.
     */
    public function receivablesAging(): array
    {
        $tickets = RepairTicket::query()
            ->where('balance', '>', 0)
            ->with(['customer' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'name')])
            ->orderByDesc('balance')
            ->get([
                'id', 'ulid', 'ticket_number', 'customer_id', 'status',
                'approved_amount', 'balance', 'promised_date', 'created_at',
            ]);

        $releasedAt = TicketEvent::query()
            ->where('event_type', 'status_changed')
            ->where('to_status', 'released')
            ->whereIn('repair_ticket_id', $tickets->pluck('id'))
            ->selectRaw('repair_ticket_id, MIN(created_at) as released_at')
            ->groupBy('repair_ticket_id')
            ->pluck('released_at', 'repair_ticket_id');

        $buckets = ['0-30' => [0, 0.0], '31-60' => [0, 0.0], '61-90' => [0, 0.0], '90+' => [0, 0.0]];

        $rows = $tickets->map(function (RepairTicket $ticket) use ($releasedAt, &$buckets) {
            [$basis, $basisDate] = match (true) {
                isset($releasedAt[$ticket->id]) => ['released', $releasedAt[$ticket->id]],
                $ticket->promised_date !== null => ['promised', $ticket->promised_date],
                default => ['created', $ticket->created_at],
            };

            $days = max(0, (int) Carbon::parse($basisDate)->diffInDays(now()));
            $bucket = match (true) {
                $days <= 30 => '0-30',
                $days <= 60 => '31-60',
                $days <= 90 => '61-90',
                default => '90+',
            };

            $balance = (float) $ticket->balance;
            $buckets[$bucket][0]++;
            $buckets[$bucket][1] = round($buckets[$bucket][1] + $balance, 2);

            return [
                'ticket_number' => $ticket->ticket_number,
                'ulid' => $ticket->ulid,
                'customer' => $ticket->customer?->name,
                'status' => $ticket->status,
                'approved_amount' => $this->money($ticket->approved_amount),
                'paid' => $this->money((float) $ticket->approved_amount - $balance),
                'balance' => $this->money($balance),
                'aging_basis' => $basis,
                'days_outstanding' => $days,
                'aging_bucket' => $bucket,
            ];
        })->values();

        return [
            'aggregate' => [
                'ticket_count' => $rows->count(),
                'total_outstanding' => $this->money($tickets->sum(fn (RepairTicket $t) => (float) $t->balance)),
                'by_bucket' => collect($buckets)->map(fn ($b) => [
                    'count' => $b[0],
                    'amount' => $this->money($b[1]),
                ]),
            ],
            'rows' => $rows,
        ];
    }

    /** Every payment method, zero-filled, from a method=>total map. */
    private function zeroedTenders(Collection $totals): array
    {
        $out = [];

        foreach (Payment::METHODS as $method) {
            $out[$method] = $this->money((float) ($totals[$method] ?? 0));
        }

        return $out;
    }

    /**
     * Money as a fixed 2-decimal string ("400.00", never "400") so these
     * owner-facing financial reports read the same as every `decimal:2`
     * field elsewhere in the API.
     */
    private function money(int|float $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function range(?string $from, ?string $to): array
    {
        return [
            $from ? Carbon::parse($from)->startOfDay() : now()->subDays(30)->startOfDay(),
            $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay(),
        ];
    }
}
