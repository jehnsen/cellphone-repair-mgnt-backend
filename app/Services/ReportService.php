<?php

namespace App\Services;

use App\Models\CommissionEntry;
use App\Models\RepairTicket;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\TicketEvent;
use App\Models\User;
use App\Models\WarrantyClaim;
use Illuminate\Support\Carbon;

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

    private function range(?string $from, ?string $to): array
    {
        return [
            $from ? Carbon::parse($from)->startOfDay() : now()->subDays(30)->startOfDay(),
            $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay(),
        ];
    }
}
