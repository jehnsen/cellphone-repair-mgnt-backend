<?php

use App\Models\Branch;
use App\Models\Product;
use App\Models\SerializedUnit;

// A well-known valid Luhn-checksum IMEI, same one used across the suite.
const SERIALIZED_UNIT_TEST_IMEI = '490154203237518';

it('registers a serialized unit and posts a receipt movement', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $product = Product::factory()->handset()->create();

    $response = $this->withToken($token)->postJson('/api/v1/serialized-units', [
        'product_ulid' => $product->ulid,
        'branch_ulid' => $branch->ulid,
        'imei' => SERIALIZED_UNIT_TEST_IMEI,
        'condition' => 'brand_new',
        'acquisition_cost' => 8000,
        'acquisition_source' => 'supplier',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.imei', SERIALIZED_UNIT_TEST_IMEI)
        ->assertJsonPath('data.status', 'in_stock');

    $levels = $this->withToken($token)->getJson('/api/v1/inventory/levels')->assertOk();
    $row = collect($levels->json('data'))->firstWhere('product.ulid', $product->ulid);

    expect($row)->not->toBeNull();
    expect($row['on_hand_qty'])->toBe('1.00');
});

it('rejects registering a serialized unit against a non-serialized product', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $product = Product::factory()->part()->create();

    $this->withToken($token)->postJson('/api/v1/serialized-units', [
        'product_ulid' => $product->ulid,
        'branch_ulid' => $branch->ulid,
        'imei' => SERIALIZED_UNIT_TEST_IMEI,
        'condition' => 'brand_new',
        'acquisition_cost' => 500,
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('requires at least an imei or a serial number', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $product = Product::factory()->handset()->create();

    $this->withToken($token)->postJson('/api/v1/serialized-units', [
        'product_ulid' => $product->ulid,
        'branch_ulid' => $branch->ulid,
        'condition' => 'brand_new',
        'acquisition_cost' => 500,
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('rejects marking a unit sold through the plain update endpoint', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $unit = SerializedUnit::factory()->create(['branch_id' => $branch->id]);

    $this->withToken($token)->patchJson("/api/v1/serialized-units/{$unit->ulid}", [
        'status' => 'sold',
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('writing off a unit posts a -1 stock movement', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $product = Product::factory()->handset()->create();
    $unit = SerializedUnit::factory()->create(['branch_id' => $branch->id, 'product_id' => $product->id]);

    // Register the receipt first so on_hand_qty starts at 1 for this unit.
    $this->withToken($token)->postJson('/api/v1/serialized-units', [
        'product_ulid' => $product->ulid,
        'branch_ulid' => $branch->ulid,
        'imei' => SERIALIZED_UNIT_TEST_IMEI,
        'condition' => 'brand_new',
        'acquisition_cost' => 8000,
    ])->assertStatus(201);

    $this->withToken($token)->patchJson("/api/v1/serialized-units/{$unit->ulid}", [
        'status' => 'written_off',
    ])->assertOk()->assertJsonPath('data.status', 'written_off');

    $movements = $this->withToken($token)->getJson('/api/v1/inventory/movements')->assertOk();
    $types = collect($movements->json('data'))->pluck('movement_type');
    expect($types)->toContain('write_off');
});
