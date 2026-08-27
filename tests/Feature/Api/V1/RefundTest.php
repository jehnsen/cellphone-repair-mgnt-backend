<?php

use App\Models\Branch;
use App\Models\Product;
use App\Models\Shift;
use App\Models\StockLevel;

it('refunds a line with restock, putting stock back and marking the sale refunded', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = userWithRole('manager', $branch);
    Shift::factory()->open()->create(['branch_id' => $branch->id, 'cashier_id' => $user->id]);
    $product = Product::factory()->accessory()->create(['selling_price' => 100, 'track_inventory' => true]);
    StockLevel::factory()->create(['product_id' => $product->id, 'branch_id' => $branch->id, 'on_hand_qty' => 5, 'reserved_qty' => 0]);

    $sale = $this->withToken($token)->postJson('/api/v1/sales', [
        'lines' => [['sellable_type' => 'product', 'product_ulid' => $product->ulid, 'quantity' => 1]],
    ])->assertStatus(201);
    $saleUlid = $sale->json('data.ulid');

    $response = $this->withToken($token)->postJson("/api/v1/sales/{$saleUlid}/refunds", [
        'reason_code' => 'customer_changed_mind',
        'lines' => [
            ['line_index' => 0, 'quantity' => 1, 'restock_behavior' => 'restock'],
        ],
    ]);

    $response->assertStatus(201)->assertJsonPath('data.reason_code', 'customer_changed_mind');

    $sale = $this->withToken($token)->getJson("/api/v1/sales/{$saleUlid}")->assertOk();
    expect($sale->json('data.status'))->toBe('refunded');

    // Sold 1 out of 5 (down to 4), then restocked it via the refund — back to 5.
    $levels = $this->withToken($token)->getJson('/api/v1/inventory/levels')->assertOk();
    $row = collect($levels->json('data'))->firstWhere('product.ulid', $product->ulid);
    expect($row['on_hand_qty'])->toBe('5.00');
});

it('a write_off refund does not restock', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = userWithRole('manager', $branch);
    Shift::factory()->open()->create(['branch_id' => $branch->id, 'cashier_id' => $user->id]);
    $product = Product::factory()->accessory()->create(['selling_price' => 100, 'track_inventory' => true]);
    StockLevel::factory()->create(['product_id' => $product->id, 'branch_id' => $branch->id, 'on_hand_qty' => 5, 'reserved_qty' => 0]);

    $sale = $this->withToken($token)->postJson('/api/v1/sales', [
        'lines' => [['sellable_type' => 'product', 'product_ulid' => $product->ulid, 'quantity' => 1]],
    ])->assertStatus(201);
    $saleUlid = $sale->json('data.ulid');

    $this->withToken($token)->postJson("/api/v1/sales/{$saleUlid}/refunds", [
        'reason_code' => 'defective',
        'lines' => [
            ['line_index' => 0, 'quantity' => 1, 'restock_behavior' => 'write_off'],
        ],
    ])->assertStatus(201);

    $levels = $this->withToken($token)->getJson('/api/v1/inventory/levels')->assertOk();
    $row = collect($levels->json('data'))->firstWhere('product.ulid', $product->ulid);
    expect($row['on_hand_qty'])->toBe('4.00');
});

it('rejects refunding more than a line still has remaining', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = userWithRole('manager', $branch);
    Shift::factory()->open()->create(['branch_id' => $branch->id, 'cashier_id' => $user->id]);
    $product = Product::factory()->accessory()->create(['selling_price' => 100, 'track_inventory' => false]);

    $sale = $this->withToken($token)->postJson('/api/v1/sales', [
        'lines' => [['sellable_type' => 'product', 'product_ulid' => $product->ulid, 'quantity' => 1]],
    ])->assertStatus(201);
    $saleUlid = $sale->json('data.ulid');

    $this->withToken($token)->postJson("/api/v1/sales/{$saleUlid}/refunds", [
        'reason_code' => 'customer_changed_mind',
        'lines' => [
            ['line_index' => 0, 'quantity' => 5, 'restock_behavior' => 'restock'],
        ],
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('rejects an out-of-range line_index', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = userWithRole('manager', $branch);
    Shift::factory()->open()->create(['branch_id' => $branch->id, 'cashier_id' => $user->id]);
    $product = Product::factory()->accessory()->create(['selling_price' => 100, 'track_inventory' => false]);

    $sale = $this->withToken($token)->postJson('/api/v1/sales', [
        'lines' => [['sellable_type' => 'product', 'product_ulid' => $product->ulid, 'quantity' => 1]],
    ])->assertStatus(201);
    $saleUlid = $sale->json('data.ulid');

    $this->withToken($token)->postJson("/api/v1/sales/{$saleUlid}/refunds", [
        'reason_code' => 'customer_changed_mind',
        'lines' => [
            ['line_index' => 5, 'quantity' => 1, 'restock_behavior' => 'restock'],
        ],
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('forbids a technician (no sales.refund) from processing a refund', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = userWithRole('technician', $branch);
    Shift::factory()->open()->create(['branch_id' => $branch->id, 'cashier_id' => $user->id]);

    $sale = \App\Models\Sale::factory()->create(['branch_id' => $branch->id]);
    \App\Models\SaleLine::factory()->create(['sale_id' => $sale->id, 'quantity' => 1, 'amount' => 100]);

    $this->withToken($token)->postJson("/api/v1/sales/{$sale->ulid}/refunds", [
        'reason_code' => 'customer_changed_mind',
        'lines' => [['line_index' => 0, 'quantity' => 1, 'restock_behavior' => 'restock']],
    ])->assertStatus(403);
});
