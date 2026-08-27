<?php

use App\Models\Branch;
use App\Models\Product;

it('posts a stock adjustment and moves the ledger and stock level together', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $product = Product::factory()->part()->create();

    $response = $this->withToken($token)->postJson('/api/v1/stock-adjustments', [
        'branch_ulid' => $branch->ulid,
        'reason_code' => 'count_variance',
        'note' => 'Physical count found 10 extra units.',
        'lines' => [
            ['product_ulid' => $product->ulid, 'quantity_delta' => 10, 'unit_cost' => 50],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.reason_code', 'count_variance')
        ->assertJsonPath('data.lines.0.quantity_delta', '10.00');

    $levels = $this->withToken($token)->getJson('/api/v1/inventory/levels')->assertOk();
    $row = collect($levels->json('data'))->firstWhere('product.ulid', $product->ulid);
    expect($row['on_hand_qty'])->toBe('10.00');

    $movements = $this->withToken($token)->getJson('/api/v1/inventory/movements')->assertOk();
    $first = collect($movements->json('data'))->firstWhere('product.ulid', $product->ulid);
    expect($first['movement_type'])->toBe('adjustment');
    expect($first['reference_type'])->toBe('stock_adjustment');
    expect($first['balance_after'])->toBe('10.00');
});

it('rejects a zero quantity_delta line', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $product = Product::factory()->part()->create();

    $this->withToken($token)->postJson('/api/v1/stock-adjustments', [
        'branch_ulid' => $branch->ulid,
        'reason_code' => 'count_variance',
        'lines' => [
            ['product_ulid' => $product->ulid, 'quantity_delta' => 0, 'unit_cost' => 50],
        ],
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('rejects a serialized-unit line adjusting by more than one', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $product = Product::factory()->handset()->create();
    $unit = \App\Models\SerializedUnit::factory()->create(['branch_id' => $branch->id, 'product_id' => $product->id]);

    $this->withToken($token)->postJson('/api/v1/stock-adjustments', [
        'branch_ulid' => $branch->ulid,
        'reason_code' => 'damage',
        'lines' => [
            ['product_ulid' => $product->ulid, 'serialized_unit_ulid' => $unit->ulid, 'quantity_delta' => 2, 'unit_cost' => 8000],
        ],
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('requires the inventory.adjust permission, not just inventory.view', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('technician', $branch);
    $product = Product::factory()->part()->create();

    $this->withToken($token)->postJson('/api/v1/stock-adjustments', [
        'branch_ulid' => $branch->ulid,
        'reason_code' => 'count_variance',
        'lines' => [
            ['product_ulid' => $product->ulid, 'quantity_delta' => 5, 'unit_cost' => 50],
        ],
    ])->assertStatus(403);
});
