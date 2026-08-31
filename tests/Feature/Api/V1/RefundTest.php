<?php

use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\Customer;
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
        'refund_method' => 'cash',
        'lines' => [
            ['line_index' => 0, 'quantity' => 1, 'restock_behavior' => 'restock'],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.reason_code', 'customer_changed_mind')
        ->assertJsonPath('data.refund_method', 'cash')
        ->assertJsonPath('data.total_amount', '100.00');

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
        'refund_method' => 'cash',
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
        'refund_method' => 'cash',
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
        'refund_method' => 'cash',
        'lines' => [
            ['line_index' => 5, 'quantity' => 1, 'restock_behavior' => 'restock'],
        ],
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('a cash refund writes a drawer-out cash movement that shift close subtracts', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = userWithRole('manager', $branch);
    $shift = Shift::factory()->open()->create(['branch_id' => $branch->id, 'cashier_id' => $user->id, 'opening_float' => 1000]);
    $product = Product::factory()->accessory()->create(['selling_price' => 100, 'track_inventory' => false]);

    $sale = $this->withToken($token)->postJson('/api/v1/sales', [
        'lines' => [['sellable_type' => 'product', 'product_ulid' => $product->ulid, 'quantity' => 1]],
    ])->assertStatus(201);
    $saleUlid = $sale->json('data.ulid');

    $this->withToken($token)->postJson("/api/v1/sales/{$saleUlid}/payments", [
        'method' => 'cash', 'amount' => 100, 'tendered' => 100,
    ])->assertStatus(201);

    $this->withToken($token)->postJson("/api/v1/sales/{$saleUlid}/refunds", [
        'reason_code' => 'defective',
        'refund_method' => 'cash',
        'lines' => [['line_index' => 0, 'quantity' => 1, 'restock_behavior' => 'no_restock']],
    ])->assertStatus(201);

    $out = CashMovement::where('shift_id', $shift->id)->where('direction', 'out')->get();
    expect($out)->toHaveCount(1)
        ->and((float) $out->first()->amount)->toBe(100.0);

    // 1000 opening + 100 cash payment - 100 cash refund = 1000.
    $this->withToken($token)->postJson("/api/v1/shifts/{$shift->ulid}/close", [
        'counted_cash' => 1000,
    ])->assertOk()
        ->assertJsonPath('data.expected_cash', '1000.00')
        ->assertJsonPath('data.variance', '0.00');
});

it('rejects a cash refund when the cashier has no open shift', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = userWithRole('manager', $branch);
    $shift = Shift::factory()->open()->create(['branch_id' => $branch->id, 'cashier_id' => $user->id]);
    $product = Product::factory()->accessory()->create(['selling_price' => 100, 'track_inventory' => false]);

    $sale = $this->withToken($token)->postJson('/api/v1/sales', [
        'lines' => [['sellable_type' => 'product', 'product_ulid' => $product->ulid, 'quantity' => 1]],
    ])->assertStatus(201);
    $saleUlid = $sale->json('data.ulid');

    $this->withToken($token)->postJson("/api/v1/shifts/{$shift->ulid}/close", ['counted_cash' => 0])->assertOk();

    $this->withToken($token)->postJson("/api/v1/sales/{$saleUlid}/refunds", [
        'reason_code' => 'defective',
        'refund_method' => 'cash',
        'lines' => [['line_index' => 0, 'quantity' => 1, 'restock_behavior' => 'no_restock']],
    ])->assertStatus(409)->assertJsonPath('error.code', 'SHIFT_NOT_OPEN');
});

it('a store_credit refund issues credit onto the customer account', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = userWithRole('manager', $branch);
    Shift::factory()->open()->create(['branch_id' => $branch->id, 'cashier_id' => $user->id]);
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);
    $product = Product::factory()->accessory()->create(['selling_price' => 250, 'track_inventory' => false]);

    $sale = $this->withToken($token)->postJson('/api/v1/sales', [
        'customer_ulid' => $customer->ulid,
        'lines' => [['sellable_type' => 'product', 'product_ulid' => $product->ulid, 'quantity' => 1]],
    ])->assertStatus(201);
    $saleUlid = $sale->json('data.ulid');

    $this->withToken($token)->postJson("/api/v1/sales/{$saleUlid}/refunds", [
        'reason_code' => 'customer_changed_mind',
        'refund_method' => 'store_credit',
        'lines' => [['line_index' => 0, 'quantity' => 1, 'restock_behavior' => 'no_restock']],
    ])->assertStatus(201)->assertJsonPath('data.refund_method', 'store_credit');

    $this->withToken($token)->getJson("/api/v1/customers/{$customer->ulid}/store-credit")
        ->assertOk()
        ->assertJsonPath('data.balance', '250.00')
        ->assertJsonPath('data.entries.0.direction', 'credit')
        ->assertJsonPath('data.entries.0.reason', 'refund');
});

it('rejects a store_credit refund on a walk-in sale with no customer', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = userWithRole('manager', $branch);
    Shift::factory()->open()->create(['branch_id' => $branch->id, 'cashier_id' => $user->id]);
    $product = Product::factory()->accessory()->create(['selling_price' => 100, 'track_inventory' => false]);

    $sale = $this->withToken($token)->postJson('/api/v1/sales', [
        'lines' => [['sellable_type' => 'product', 'product_ulid' => $product->ulid, 'quantity' => 1]],
    ])->assertStatus(201);
    $saleUlid = $sale->json('data.ulid');

    $this->withToken($token)->postJson("/api/v1/sales/{$saleUlid}/refunds", [
        'reason_code' => 'defective',
        'refund_method' => 'store_credit',
        'lines' => [['line_index' => 0, 'quantity' => 1, 'restock_behavior' => 'no_restock']],
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
