<?php

use App\Models\Branch;
use App\Models\Product;
use App\Models\RepairTicket;
use App\Models\Service;
use App\Models\TicketLine;

it('adds a part line and recalculates the amount', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id]);
    $product = Product::factory()->create();

    $response = $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/lines", [
        'line_type' => 'part',
        'product_ulid' => $product->ulid,
        'description' => 'Replacement screen',
        'quantity' => 1,
        'unit_cost' => 500,
        'unit_price' => 900,
    ]);

    $response->assertStatus(201)->assertJsonPath('data.amount', '900.00');
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
