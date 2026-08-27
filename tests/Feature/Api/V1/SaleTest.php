<?php

use App\Models\Branch;
use App\Models\Product;
use App\Models\SerializedUnit;
use App\Models\Service;
use App\Models\Shift;
use App\Models\StockLevel;

function openShiftFor(string $role, Branch $branch): array
{
    [$user, $token] = userWithRole($role, $branch);
    Shift::factory()->open()->create(['branch_id' => $branch->id, 'cashier_id' => $user->id]);

    return [$user, $token];
}

it('creates a sale for a product line, computing VAT and consuming stock', function () {
    $branch = Branch::factory()->create(['vat_registered' => true]);
    [, $token] = openShiftFor('cashier', $branch);
    $product = Product::factory()->accessory()->create(['selling_price' => 112, 'cost' => 50, 'track_inventory' => true]);
    StockLevel::factory()->create(['product_id' => $product->id, 'branch_id' => $branch->id, 'on_hand_qty' => 10, 'reserved_qty' => 0]);

    $response = $this->withToken($token)->postJson('/api/v1/sales', [
        'lines' => [
            ['sellable_type' => 'product', 'product_ulid' => $product->ulid, 'quantity' => 1],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.subtotal', '112.00')
        ->assertJsonPath('data.vat_amount', '12.00')
        ->assertJsonPath('data.vatable_sales', '100.00')
        ->assertJsonPath('data.total', '112.00');

    $levels = $this->withToken($token)->getJson('/api/v1/inventory/levels')->assertOk();
    $row = collect($levels->json('data'))->firstWhere('product.ulid', $product->ulid);
    expect($row['on_hand_qty'])->toBe('9.00');
});

it('applies a line-scope percent discount', function () {
    $branch = Branch::factory()->create();
    [, $token] = openShiftFor('cashier', $branch);
    $product = Product::factory()->accessory()->create(['selling_price' => 100, 'track_inventory' => false]);

    $this->withToken($token)->postJson('/api/v1/sales', [
        'lines' => [
            ['sellable_type' => 'product', 'product_ulid' => $product->ulid, 'quantity' => 1, 'discount' => ['type' => 'percent', 'value' => 10]],
        ],
    ])->assertStatus(201)
        ->assertJsonPath('data.subtotal', '100.00')
        ->assertJsonPath('data.discount_total', '10.00')
        ->assertJsonPath('data.total', '90.00');
});

it('applies a sale-scope senior citizen discount as VAT-exempt plus 20% off', function () {
    $branch = Branch::factory()->create(['vat_registered' => true]);
    [, $token] = openShiftFor('cashier', $branch);
    $product = Product::factory()->accessory()->create(['selling_price' => 112, 'track_inventory' => false]);

    $response = $this->withToken($token)->postJson('/api/v1/sales', [
        'lines' => [
            ['sellable_type' => 'product', 'product_ulid' => $product->ulid, 'quantity' => 1],
        ],
        'sale_discount' => [
            'type' => 'senior_citizen',
            'id_type' => 'OSCA ID',
            'id_number' => 'OSCA-12345',
            'cardholder_name' => 'Juan Dela Cruz',
        ],
    ]);

    // 112 gross -> 100 net of VAT -> 80 after the mandatory 20% senior discount.
    $response->assertStatus(201)
        ->assertJsonPath('data.vatable_sales', '0.00')
        ->assertJsonPath('data.vat_amount', '0.00')
        ->assertJsonPath('data.vat_exempt_sales', '80.00')
        ->assertJsonPath('data.total', '80.00');
});

it('sells a serialized unit, flips its status, and rejects selling it twice', function () {
    $branch = Branch::factory()->create();
    [, $token] = openShiftFor('cashier', $branch);
    $product = Product::factory()->handset()->create(['selling_price' => 11200]);
    $unit = SerializedUnit::factory()->create(['branch_id' => $branch->id, 'product_id' => $product->id]);

    $this->withToken($token)->postJson('/api/v1/sales', [
        'lines' => [
            ['sellable_type' => 'serialized_unit', 'serialized_unit_ulid' => $unit->ulid],
        ],
    ])->assertStatus(201)->assertJsonPath('data.total', '11200.00');

    expect($unit->fresh()->status)->toBe('sold');

    $this->withToken($token)->postJson('/api/v1/sales', [
        'lines' => [
            ['sellable_type' => 'serialized_unit', 'serialized_unit_ulid' => $unit->ulid],
        ],
    ])->assertStatus(409)->assertJsonPath('error.code', 'UNIT_ALREADY_SOLD');
});

it('sells a service line with no stock effect', function () {
    $branch = Branch::factory()->create();
    [, $token] = openShiftFor('cashier', $branch);
    $service = Service::factory()->create(['default_price' => 500]);

    $this->withToken($token)->postJson('/api/v1/sales', [
        'lines' => [
            ['sellable_type' => 'service', 'service_ulid' => $service->ulid, 'quantity' => 1],
        ],
    ])->assertStatus(201)->assertJsonPath('data.total', '500.00');
});

it('rejects a sale when stock is insufficient', function () {
    $branch = Branch::factory()->create();
    [, $token] = openShiftFor('cashier', $branch);
    $product = Product::factory()->part()->create(['track_inventory' => true]);
    StockLevel::factory()->create(['product_id' => $product->id, 'branch_id' => $branch->id, 'on_hand_qty' => 1, 'reserved_qty' => 0]);

    $this->withToken($token)->postJson('/api/v1/sales', [
        'lines' => [
            ['sellable_type' => 'product', 'product_ulid' => $product->ulid, 'quantity' => 5],
        ],
    ])->assertStatus(422)->assertJsonPath('error.code', 'INSUFFICIENT_STOCK');
});

it('rejects a sale when the cashier has no open shift', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('cashier', $branch);
    $product = Product::factory()->accessory()->create(['track_inventory' => false]);

    $this->withToken($token)->postJson('/api/v1/sales', [
        'lines' => [
            ['sellable_type' => 'product', 'product_ulid' => $product->ulid, 'quantity' => 1],
        ],
    ])->assertStatus(409)->assertJsonPath('error.code', 'SHIFT_NOT_OPEN');
});

it('voids a completed sale, restocking the product', function () {
    $branch = Branch::factory()->create();
    [, $token] = openShiftFor('manager', $branch);
    $product = Product::factory()->part()->create(['track_inventory' => true]);
    StockLevel::factory()->create(['product_id' => $product->id, 'branch_id' => $branch->id, 'on_hand_qty' => 10, 'reserved_qty' => 0]);

    $created = $this->withToken($token)->postJson('/api/v1/sales', [
        'lines' => [
            ['sellable_type' => 'product', 'product_ulid' => $product->ulid, 'quantity' => 3],
        ],
    ])->assertStatus(201);

    $saleUlid = $created->json('data.ulid');

    $this->withToken($token)->postJson("/api/v1/sales/{$saleUlid}/void", [
        'void_reason' => 'Customer changed their mind before leaving the store.',
    ])->assertOk()->assertJsonPath('data.status', 'voided');

    $levels = $this->withToken($token)->getJson('/api/v1/inventory/levels')->assertOk();
    $row = collect($levels->json('data'))->firstWhere('product.ulid', $product->ulid);
    expect($row['on_hand_qty'])->toBe('10.00');
});

it('records a split payment against a sale and rejects overpayment', function () {
    $branch = Branch::factory()->create();
    [, $token] = openShiftFor('cashier', $branch);
    $product = Product::factory()->accessory()->create(['selling_price' => 1000, 'track_inventory' => false]);

    $created = $this->withToken($token)->postJson('/api/v1/sales', [
        'lines' => [
            ['sellable_type' => 'product', 'product_ulid' => $product->ulid, 'quantity' => 1],
        ],
    ])->assertStatus(201);

    $saleUlid = $created->json('data.ulid');
    $total = (float) $created->json('data.total');

    $this->withToken($token)->postJson("/api/v1/sales/{$saleUlid}/payments", [
        'method' => 'cash',
        'amount' => $total - 200,
    ])->assertStatus(201);

    $this->withToken($token)->postJson("/api/v1/sales/{$saleUlid}/payments", [
        'method' => 'gcash',
        'amount' => 300,
        'reference_number' => 'GC12345',
    ])->assertStatus(409)->assertJsonPath('error.code', 'PAYMENT_SUM_MISMATCH');

    $this->withToken($token)->postJson("/api/v1/sales/{$saleUlid}/payments", [
        'method' => 'gcash',
        'amount' => 200,
        'reference_number' => 'GC12345',
    ])->assertStatus(201);
});
