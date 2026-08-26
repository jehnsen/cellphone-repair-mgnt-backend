<?php

use App\Models\DeviceBrand;
use App\Models\DeviceModel;
use App\Models\ProductCategory;

it('creates a device model by resolving device_brand_ulid', function () {
    [, $token] = userWithRole('manager');
    $brand = DeviceBrand::factory()->create(['name' => 'Samsung']);

    $response = $this->withToken($token)->postJson('/api/v1/device-models', [
        'device_brand_ulid' => $brand->ulid,
        'name' => 'Galaxy A15',
        'release_year' => 2024,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Galaxy A15')
        ->assertJsonPath('data.brand.ulid', $brand->ulid);

    expect(DeviceModel::where('name', 'Galaxy A15')->first()->device_brand_id)->toBe($brand->id);
});

// Split into two tests rather than chaining an owner call then a cashier
// call in one: Sanctum's guard caches the resolved user for the rest of
// the test process once authenticated, so a second withToken() call with a
// different bearer token in the same test doesn't reliably re-authenticate.
it('shows product cost to a user with reports.margin.view', function () {
    [, $ownerToken] = userWithRole('owner');

    $category = ProductCategory::factory()->create();
    $product = \App\Models\Product::factory()->create([
        'product_category_id' => $category->id,
        'cost' => 1234.56,
        'selling_price' => 1999.00,
    ]);

    $this->withToken($ownerToken)->getJson("/api/v1/products/{$product->ulid}")
        ->assertOk()
        ->assertJsonPath('data.cost', '1234.56');
});

it('hides product cost from a user without reports.margin.view', function () {
    [, $cashierToken] = userWithRole('cashier');

    $category = ProductCategory::factory()->create();
    $product = \App\Models\Product::factory()->create([
        'product_category_id' => $category->id,
        'cost' => 1234.56,
        'selling_price' => 1999.00,
    ]);

    $this->withToken($cashierToken)->getJson("/api/v1/products/{$product->ulid}")
        ->assertOk()
        ->assertJsonMissingPath('data.cost')
        ->assertJsonPath('data.selling_price', '1999.00');
});

it('attaches compatible device models to a part via ulids', function () {
    [, $token] = userWithRole('manager');
    $category = ProductCategory::factory()->create();
    $model = DeviceModel::factory()->create();

    $response = $this->withToken($token)->postJson('/api/v1/products', [
        'sku' => 'PART-0001',
        'name' => 'Replacement Screen',
        'product_category_ulid' => $category->ulid,
        'type' => 'part',
        'cost' => 500,
        'selling_price' => 900,
        'compatible_device_model_ulids' => [$model->ulid],
    ]);

    $response->assertStatus(201);

    $product = \App\Models\Product::where('sku', 'PART-0001')->firstOrFail();
    expect($product->compatibleDeviceModels()->pluck('device_models.id'))->toContain($model->id);
});
