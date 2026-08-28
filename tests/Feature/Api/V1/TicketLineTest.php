<?php

use App\Models\Branch;
use App\Models\Product;
use App\Models\RepairTicket;
use App\Models\Service;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\TicketLine;

it('adds a part line, recalculates the amount, and consumes the part from stock', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id]);
    $product = Product::factory()->create(['track_inventory' => true]);
    StockLevel::factory()->create(['product_id' => $product->id, 'branch_id' => $branch->id, 'on_hand_qty' => 5, 'reserved_qty' => 0]);

    $response = $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/lines", [
        'line_type' => 'part',
        'product_ulid' => $product->ulid,
        'description' => 'Replacement screen',
        'quantity' => 1,
        'unit_cost' => 500,
        'unit_price' => 900,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.amount', '900.00')
        ->assertJsonPath('data.stock_consumed', true);

    expect(StockLevel::withoutGlobalScopes()->where('product_id', $product->id)->where('branch_id', $branch->id)->first()->on_hand_qty)
        ->toBe('4.00');
    expect(StockMovement::withoutGlobalScopes()->where('product_id', $product->id)->where('movement_type', 'ticket_consumption')->exists())
        ->toBeTrue();
    expect(TicketLine::first()->stock_movement_id)->not->toBeNull();
});

it('rejects a part line when there is not enough stock on hand', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id]);
    $product = Product::factory()->create(['track_inventory' => true]);
    StockLevel::factory()->create(['product_id' => $product->id, 'branch_id' => $branch->id, 'on_hand_qty' => 0, 'reserved_qty' => 0]);

    $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/lines", [
        'line_type' => 'part',
        'product_ulid' => $product->ulid,
        'description' => 'Replacement screen',
        'quantity' => 1,
        'unit_cost' => 500,
        'unit_price' => 900,
    ])->assertStatus(422)->assertJsonPath('error.code', 'INSUFFICIENT_STOCK');

    expect(TicketLine::count())->toBe(0);
});

it('adds a part line for an untracked product without touching stock', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id]);
    $product = Product::factory()->create(['track_inventory' => false]);

    $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/lines", [
        'line_type' => 'part',
        'product_ulid' => $product->ulid,
        'description' => 'Miscellaneous adhesive',
        'quantity' => 1,
        'unit_price' => 50,
    ])->assertStatus(201)->assertJsonPath('data.stock_consumed', false);

    expect(StockMovement::withoutGlobalScopes()->where('product_id', $product->id)->exists())->toBeFalse();
});

it('adds a labor line tied to a service instead of a product', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id]);
    $service = Service::factory()->create();

    $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/lines", [
        'line_type' => 'labor',
        'service_ulid' => $service->ulid,
        'description' => 'Diagnostic fee',
        'quantity' => 1,
        'unit_price' => 150,
    ])->assertStatus(201)->assertJsonPath('data.line_type', 'labor');
});

it('rejects a part line that also supplies a service_ulid', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id]);
    $product = Product::factory()->create();
    $service = Service::factory()->create();

    $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/lines", [
        'line_type' => 'part',
        'product_ulid' => $product->ulid,
        'service_ulid' => $service->ulid,
        'description' => 'Bad line',
        'quantity' => 1,
        'unit_price' => 100,
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('hides unit_cost from a role without margin visibility', function () {
    // Sanctum's RequestGuard caches the resolved user for the rest of the
    // test process — seed the line directly rather than authenticating as
    // a second actor (manager) in the same test as the cashier under test.
    $branch = Branch::factory()->create();
    [, $cashierToken] = userWithRole('cashier', $branch);
    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id]);
    TicketLine::factory()->part()->create([
        'repair_ticket_id' => $ticket->id,
        'unit_cost' => 300,
        'unit_price' => 600,
    ]);

    $response = $this->withToken($cashierToken)->getJson("/api/v1/tickets/{$ticket->ulid}/lines")->assertOk();

    expect($response->json('data.0'))->not->toHaveKey('unit_cost');
});
