<?php

use App\Models\Branch;
use App\Models\Product;
use App\Models\Shift;

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
    \App\Models\RepairTicket::factory()->create(['branch_id' => $branch->id, 'status' => 'unclaimed']);

    $response = $this->withToken($token)->getJson('/api/v1/reports/unclaimed-aging')->assertOk();

    expect($response->json('data.rows'))->toHaveCount(1);
});
