<?php

use App\Models\Branch;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;

it('creates a purchase order in draft status', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->part()->create();

    $this->withToken($token)->postJson('/api/v1/purchase-orders', [
        'branch_ulid' => $branch->ulid,
        'supplier_ulid' => $supplier->ulid,
        'lines' => [
            ['product_ulid' => $product->ulid, 'ordered_qty' => 20, 'unit_cost' => 50],
        ],
    ])->assertStatus(201)
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.lines.0.ordered_qty', '20.00');
});

it('submits, then receives a purchase order, posting stock and syncing status', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->part()->create();

    $created = $this->withToken($token)->postJson('/api/v1/purchase-orders', [
        'branch_ulid' => $branch->ulid,
        'supplier_ulid' => $supplier->ulid,
        'lines' => [
            ['product_ulid' => $product->ulid, 'ordered_qty' => 10, 'unit_cost' => 50],
        ],
    ])->assertStatus(201);
    $ulid = $created->json('data.ulid');

    $this->withToken($token)->patchJson("/api/v1/purchase-orders/{$ulid}", ['status' => 'submitted'])
        ->assertOk()->assertJsonPath('data.status', 'submitted');

    $this->withToken($token)->postJson("/api/v1/purchase-orders/{$ulid}/receive", [
        'lines' => [
            ['product_ulid' => $product->ulid, 'quantity' => 6],
        ],
    ])->assertStatus(201)->assertJsonPath('data.lines.0.quantity', '6.00');

    $show = $this->withToken($token)->getJson("/api/v1/purchase-orders/{$ulid}")->assertOk();
    expect($show->json('data.status'))->toBe('partially_received');
    expect($show->json('data.lines.0.received_qty'))->toBe('6.00');

    $levels = $this->withToken($token)->getJson('/api/v1/inventory/levels')->assertOk();
    $row = collect($levels->json('data'))->firstWhere('product.ulid', $product->ulid);
    expect($row['on_hand_qty'])->toBe('6.00');

    // Receiving the remainder should flip the PO fully received.
    $this->withToken($token)->postJson("/api/v1/purchase-orders/{$ulid}/receive", [
        'lines' => [
            ['product_ulid' => $product->ulid, 'quantity' => 4],
        ],
    ])->assertStatus(201);

    $show = $this->withToken($token)->getJson("/api/v1/purchase-orders/{$ulid}")->assertOk();
    expect($show->json('data.status'))->toBe('received');
});

it('rejects receiving a draft purchase order', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $po = PurchaseOrder::factory()->create(['branch_id' => $branch->id, 'status' => 'draft']);
    $product = Product::factory()->part()->create();
    \App\Models\PurchaseOrderLine::factory()->create(['purchase_order_id' => $po->id, 'product_id' => $product->id, 'ordered_qty' => 5]);

    $this->withToken($token)->postJson("/api/v1/purchase-orders/{$po->ulid}/receive", [
        'lines' => [['product_ulid' => $product->ulid, 'quantity' => 1]],
    ])->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATUS_TRANSITION');
});

it('rejects receiving more than what is outstanding on a line', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $po = PurchaseOrder::factory()->create(['branch_id' => $branch->id, 'status' => 'submitted']);
    $product = Product::factory()->part()->create();
    \App\Models\PurchaseOrderLine::factory()->create(['purchase_order_id' => $po->id, 'product_id' => $product->id, 'ordered_qty' => 5, 'received_qty' => 0]);

    $this->withToken($token)->postJson("/api/v1/purchase-orders/{$po->ulid}/receive", [
        'lines' => [['product_ulid' => $product->ulid, 'quantity' => 10]],
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('does ad-hoc goods receiving without a purchase order', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->part()->create();

    $this->withToken($token)->postJson('/api/v1/goods-receipts', [
        'branch_ulid' => $branch->ulid,
        'supplier_ulid' => $supplier->ulid,
        'lines' => [
            ['product_ulid' => $product->ulid, 'quantity' => 15, 'unit_cost' => 40],
        ],
    ])->assertStatus(201)->assertJsonPath('data.status', 'posted');

    $levels = $this->withToken($token)->getJson('/api/v1/inventory/levels')->assertOk();
    $row = collect($levels->json('data'))->firstWhere('product.ulid', $product->ulid);
    expect($row['on_hand_qty'])->toBe('15.00');
});
