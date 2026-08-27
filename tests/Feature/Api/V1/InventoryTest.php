<?php

use App\Models\Branch;
use App\Models\Product;
use App\Models\StockLevel;

it('lists stock levels with the product eager-loaded', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('cashier', $branch);
    $product = Product::factory()->create();
    StockLevel::factory()->create(['product_id' => $product->id, 'branch_id' => $branch->id, 'on_hand_qty' => 25]);

    $response = $this->withToken($token)->getJson('/api/v1/inventory/levels')->assertOk();

    $row = collect($response->json('data'))->firstWhere('product.ulid', $product->ulid);
    expect($row)->not->toBeNull();
    expect($row['on_hand_qty'])->toBe('25.00');
    expect((float) $row['available_qty'])->toBe(25.0);
});

it('cursor-paginates the stock movement ledger', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('cashier', $branch);

    $response = $this->withToken($token)->getJson('/api/v1/inventory/movements')->assertOk();

    expect($response->json())->toHaveKey('meta');
    expect($response->json('meta'))->toHaveKey('next_cursor');
});
