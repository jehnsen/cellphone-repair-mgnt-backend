<?php

use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Refund;
use App\Models\RepairTicket;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\TicketEvent;
use App\Models\TicketLine;
use App\Models\User;

it('reports sales aggregates for completed sales', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = userWithRole('manager', $branch);
    Shift::factory()->open()->create(['branch_id' => $branch->id, 'cashier_id' => $user->id]);
    $product = Product::factory()->accessory()->create(['selling_price' => 500, 'track_inventory' => false]);

    $this->withToken($token)->postJson('/api/v1/sales', [
        'lines' => [['sellable_type' => 'product', 'product_ulid' => $product->ulid, 'quantity' => 1]],
    ])->assertStatus(201);

    $response = $this->withToken($token)->getJson('/api/v1/reports/sales')->assertOk();

    expect((float) $response->json('data.aggregate.gross_sales'))->toBeGreaterThanOrEqual(500.0);
    expect($response->json('meta.generated_at'))->toBeString();
});

it('forbids margin reports without reports.margin.view', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('cashier', $branch);

    $this->withToken($token)->getJson('/api/v1/reports/margin')->assertStatus(403);
});

it('allows a manager to view margin reports', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);

    $this->withToken($token)->getJson('/api/v1/reports/margin')->assertOk();
});

it('forbids reports entirely for a role without reports.view', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = userWithRole('technician', $branch);

    $this->withToken($token)->getJson('/api/v1/reports/sales')->assertStatus(403);
});

it('reports unclaimed ticket aging', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    RepairTicket::factory()->create(['branch_id' => $branch->id, 'status' => 'unclaimed']);

    $response = $this->withToken($token)->getJson('/api/v1/reports/unclaimed-aging')->assertOk();

    expect($response->json('data.rows'))->toHaveCount(1);
});

it('forbids the repair P&L report without reports.margin.view', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('cashier', $branch);

    $this->withToken($token)->getJson('/api/v1/reports/repair-pnl')->assertStatus(403);
});

it('reports repair P&L splitting parts and labour revenue against parts cost', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $tech = User::factory()->create(['branch_id' => $branch->id]);

    $ticket = RepairTicket::factory()->create([
        'branch_id' => $branch->id,
        'assigned_technician_id' => $tech->id,
        'status' => 'released',
    ]);

    TicketLine::factory()->part()->create([
        'repair_ticket_id' => $ticket->id,
        'quantity' => 1, 'unit_cost' => 400, 'unit_price' => 1000, 'amount' => 1000,
    ]);
    TicketLine::factory()->create([
        'repair_ticket_id' => $ticket->id,
        'quantity' => 1, 'unit_price' => 800, 'amount' => 800,
    ]);

    TicketEvent::factory()->create([
        'repair_ticket_id' => $ticket->id,
        'event_type' => 'status_changed',
        'to_status' => 'released',
        'created_at' => now(),
    ]);

    Payment::factory()->create([
        'payable_type' => 'repair_ticket', 'payable_id' => $ticket->id,
        'method' => 'cash', 'amount' => 1800, 'shift_id' => null,
    ]);

    $response = $this->withToken($token)->getJson('/api/v1/reports/repair-pnl')->assertOk();

    $response
        ->assertJsonPath('data.aggregate.tickets_released', 1)
        ->assertJsonPath('data.aggregate.parts_revenue', '1000.00')
        ->assertJsonPath('data.aggregate.labor_revenue', '800.00')
        ->assertJsonPath('data.aggregate.total_revenue', '1800.00')
        ->assertJsonPath('data.aggregate.parts_cost', '400.00')
        ->assertJsonPath('data.aggregate.gross_margin', '1400.00')
        ->assertJsonPath('data.aggregate.payments_collected', '1800.00')
        ->assertJsonPath('data.rows.0.technician', $tech->name)
        ->assertJsonPath('data.rows.0.gross_margin', '1400.00');
});

it('excludes tickets released outside the window from repair P&L', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);

    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id, 'status' => 'released']);
    TicketLine::factory()->create(['repair_ticket_id' => $ticket->id, 'amount' => 500, 'unit_price' => 500]);
    TicketEvent::factory()->create([
        'repair_ticket_id' => $ticket->id,
        'event_type' => 'status_changed', 'to_status' => 'released',
        'created_at' => now()->subDays(90),
    ]);

    $response = $this->withToken($token)->getJson('/api/v1/reports/repair-pnl')->assertOk();

    $response->assertJsonPath('data.aggregate.tickets_released', 0)
        ->assertJsonPath('data.aggregate.total_revenue', '0.00');
});

it('reports end-of-day cash reconciliation per shift with a tender breakdown', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = userWithRole('manager', $branch);

    $shift = Shift::factory()->create([
        'branch_id' => $branch->id, 'cashier_id' => $user->id,
        'opening_float' => 1000, 'opened_at' => now()->subDay(),
        'closed_at' => now(), 'counted_cash' => 1300,
    ]);

    Payment::factory()->create(['payable_type' => 'sale', 'method' => 'cash', 'amount' => 500, 'shift_id' => $shift->id]);
    Payment::factory()->gcash()->create(['payable_type' => 'sale', 'amount' => 300, 'shift_id' => $shift->id]);
    CashMovement::factory()->create(['shift_id' => $shift->id, 'direction' => 'out', 'amount' => 200, 'actor_id' => $user->id]);

    $response = $this->withToken($token)->getJson('/api/v1/reports/cash-reconciliation')->assertOk();

    // 1000 opening + 500 cash - 200 cash out = 1300; gcash never touches the drawer.
    $response
        ->assertJsonPath('data.aggregate.shift_count', 1)
        ->assertJsonPath('data.rows.0.expected_cash', '1300.00')
        ->assertJsonPath('data.rows.0.variance', '0.00')
        ->assertJsonPath('data.rows.0.tender_breakdown.cash', '500.00')
        ->assertJsonPath('data.rows.0.tender_breakdown.gcash', '300.00')
        ->assertJsonPath('data.aggregate.tender_totals.cash', '500.00');
});

it('computes a live expected_cash for a still-open shift', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = userWithRole('manager', $branch);

    $shift = Shift::factory()->open()->create([
        'branch_id' => $branch->id, 'cashier_id' => $user->id,
        'opening_float' => 500, 'opened_at' => now()->subHours(2),
    ]);
    Payment::factory()->create(['payable_type' => 'sale', 'method' => 'cash', 'amount' => 250, 'shift_id' => $shift->id]);

    $response = $this->withToken($token)->getJson('/api/v1/reports/cash-reconciliation')->assertOk();

    $response
        ->assertJsonPath('data.rows.0.status', 'open')
        ->assertJsonPath('data.rows.0.expected_cash', '750.00')
        ->assertJsonPath('data.rows.0.counted_cash', null)
        ->assertJsonPath('data.rows.0.variance', null)
        ->assertJsonPath('data.aggregate.open_shift_count', 1);
});

it('reports refunds and voids with method and reason breakdowns', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);

    $sale = Sale::factory()->create(['branch_id' => $branch->id]);
    Refund::factory()->create([
        'sale_id' => $sale->id, 'refund_method' => 'cash',
        'reason_code' => 'defective', 'total_amount' => 250,
    ]);
    Sale::factory()->create([
        'branch_id' => $branch->id, 'status' => 'voided',
        'void_reason' => 'wrong item rung up', 'total' => 999,
    ]);

    $response = $this->withToken($token)->getJson('/api/v1/reports/refunds-voids')->assertOk();

    $response
        ->assertJsonPath('data.aggregate.refund_count', 1)
        ->assertJsonPath('data.aggregate.refund_total', '250.00')
        ->assertJsonPath('data.aggregate.refund_by_method.cash.amount', '250.00')
        ->assertJsonPath('data.aggregate.refund_by_reason.0.reason_code', 'defective')
        ->assertJsonPath('data.aggregate.void_count', 1)
        ->assertJsonPath('data.aggregate.void_total', '999.00')
        ->assertJsonPath('data.rows.refunds.0.refund_method', 'cash')
        ->assertJsonPath('data.rows.voids.0.void_reason', 'wrong item rung up');
});

it('does not leak another branch\'s refunds or voids', function () {
    $branch = Branch::factory()->create();
    $other = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);

    $otherSale = Sale::factory()->create(['branch_id' => $other->id]);
    Refund::factory()->create(['sale_id' => $otherSale->id, 'total_amount' => 999]);
    Sale::factory()->create(['branch_id' => $other->id, 'status' => 'voided', 'total' => 500]);

    $response = $this->withToken($token)->getJson('/api/v1/reports/refunds-voids')->assertOk();

    $response
        ->assertJsonPath('data.aggregate.refund_count', 0)
        ->assertJsonPath('data.aggregate.void_count', 0);
});

it('reports outstanding repair balances aged into buckets', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);

    RepairTicket::factory()->create([
        'branch_id' => $branch->id, 'status' => 'released',
        'approved_amount' => 1000, 'balance' => 400,
        'promised_date' => now()->subDays(10),
    ]);
    // Fully settled — must not appear.
    RepairTicket::factory()->create([
        'branch_id' => $branch->id, 'status' => 'released',
        'approved_amount' => 800, 'balance' => 0,
    ]);

    $response = $this->withToken($token)->getJson('/api/v1/reports/receivables-aging')->assertOk();

    $response
        ->assertJsonPath('data.aggregate.ticket_count', 1)
        ->assertJsonPath('data.aggregate.total_outstanding', '400.00')
        ->assertJsonPath('data.aggregate.by_bucket.0-30.count', 1)
        ->assertJsonPath('data.aggregate.by_bucket.0-30.amount', '400.00')
        ->assertJsonPath('data.rows.0.balance', '400.00')
        ->assertJsonPath('data.rows.0.paid', '600.00')
        ->assertJsonPath('data.rows.0.aging_bucket', '0-30');
});
