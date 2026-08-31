<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Shift;

it('lets a manager grant store credit and shows it on the balance', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);

    $this->withToken($token)->postJson("/api/v1/customers/{$customer->ulid}/store-credit/adjust", [
        'direction' => 'credit',
        'amount' => 500,
        'reason' => 'goodwill',
    ])->assertStatus(201)
        ->assertJsonPath('data.direction', 'credit')
        ->assertJsonPath('data.balance_after', '500.00');

    $this->withToken($token)->getJson("/api/v1/customers/{$customer->ulid}/store-credit")
        ->assertOk()
        ->assertJsonPath('data.balance', '500.00');
});

it('forbids a cashier from adjusting store credit', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('cashier', $branch);
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);

    $this->withToken($token)->postJson("/api/v1/customers/{$customer->ulid}/store-credit/adjust", [
        'direction' => 'credit',
        'amount' => 500,
        'reason' => 'goodwill',
    ])->assertStatus(403);
});

it('rejects debiting more store credit than the balance holds', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);

    $this->withToken($token)->postJson("/api/v1/customers/{$customer->ulid}/store-credit/adjust", [
        'direction' => 'credit', 'amount' => 100, 'reason' => 'goodwill',
    ])->assertStatus(201);

    $this->withToken($token)->postJson("/api/v1/customers/{$customer->ulid}/store-credit/adjust", [
        'direction' => 'debit', 'amount' => 250, 'reason' => 'correction',
    ])->assertStatus(422)->assertJsonPath('error.code', 'INSUFFICIENT_STORE_CREDIT');
});

it('redeems store credit as payment against a sale and draws the balance down', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = userWithRole('manager', $branch);
    Shift::factory()->open()->create(['branch_id' => $branch->id, 'cashier_id' => $user->id]);
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);
    $product = Product::factory()->accessory()->create(['selling_price' => 300, 'track_inventory' => false]);

    $this->withToken($token)->postJson("/api/v1/customers/{$customer->ulid}/store-credit/adjust", [
        'direction' => 'credit', 'amount' => 500, 'reason' => 'goodwill',
    ])->assertStatus(201);

    $sale = $this->withToken($token)->postJson('/api/v1/sales', [
        'customer_ulid' => $customer->ulid,
        'lines' => [['sellable_type' => 'product', 'product_ulid' => $product->ulid, 'quantity' => 1]],
    ])->assertStatus(201);
    $saleUlid = $sale->json('data.ulid');

    $this->withToken($token)->postJson("/api/v1/sales/{$saleUlid}/payments", [
        'method' => 'store_credit', 'amount' => 300,
    ])->assertStatus(201)->assertJsonPath('data.method', 'store_credit');

    $this->withToken($token)->getJson("/api/v1/customers/{$customer->ulid}/store-credit")
        ->assertOk()
        ->assertJsonPath('data.balance', '200.00')
        ->assertJsonPath('data.entries.0.direction', 'debit')
        ->assertJsonPath('data.entries.0.reason', 'sale_payment');
});

it('rejects a store_credit payment when the customer has no credit', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = userWithRole('manager', $branch);
    Shift::factory()->open()->create(['branch_id' => $branch->id, 'cashier_id' => $user->id]);
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);
    $product = Product::factory()->accessory()->create(['selling_price' => 300, 'track_inventory' => false]);

    $sale = $this->withToken($token)->postJson('/api/v1/sales', [
        'customer_ulid' => $customer->ulid,
        'lines' => [['sellable_type' => 'product', 'product_ulid' => $product->ulid, 'quantity' => 1]],
    ])->assertStatus(201);
    $saleUlid = $sale->json('data.ulid');

    $this->withToken($token)->postJson("/api/v1/sales/{$saleUlid}/payments", [
        'method' => 'store_credit', 'amount' => 300,
    ])->assertStatus(422)->assertJsonPath('error.code', 'INSUFFICIENT_STORE_CREDIT');
});

it('rejects a store_credit payment on a sale with no customer', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = userWithRole('manager', $branch);
    Shift::factory()->open()->create(['branch_id' => $branch->id, 'cashier_id' => $user->id]);
    $product = Product::factory()->accessory()->create(['selling_price' => 300, 'track_inventory' => false]);

    $sale = $this->withToken($token)->postJson('/api/v1/sales', [
        'lines' => [['sellable_type' => 'product', 'product_ulid' => $product->ulid, 'quantity' => 1]],
    ])->assertStatus(201);
    $saleUlid = $sale->json('data.ulid');

    $this->withToken($token)->postJson("/api/v1/sales/{$saleUlid}/payments", [
        'method' => 'store_credit', 'amount' => 300,
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
});
