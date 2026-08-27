<?php

use App\Models\Acquisition;
use App\Models\Branch;
use App\Models\Product;

it('creates an acquisition as not_checked', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);

    $this->withToken($token)->postJson('/api/v1/acquisitions', [
        'branch_ulid' => $branch->ulid,
        'seller_name' => 'Juan Dela Cruz',
        'seller_id_type' => "Driver's License",
        'seller_id_number' => 'N01-23-456789',
        'offered_price' => 5000,
        'imei' => '490154203237518',
        'purchase_date' => now()->toDateString(),
    ])->assertStatus(201)->assertJsonPath('data.imei_check_result', 'not_checked');
});

it('runs an IMEI check and flags it', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $acquisition = Acquisition::factory()->create(['branch_id' => $branch->id]);

    $this->withToken($token)->postJson("/api/v1/acquisitions/{$acquisition->ulid}/imei-check", [
        'result' => 'flagged',
    ])->assertOk()->assertJsonPath('data.imei_check_result', 'flagged');
});

it('blocks completing a flagged acquisition', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $acquisition = Acquisition::factory()->flagged()->create(['branch_id' => $branch->id]);
    $product = Product::factory()->handset()->create();

    $this->withToken($token)->postJson("/api/v1/acquisitions/{$acquisition->ulid}/complete", [
        'product_ulid' => $product->ulid,
        'condition' => 'secondhand',
    ])->assertStatus(409)->assertJsonPath('error.code', 'ACQUISITION_IMEI_FLAGGED');
});

it('completes a clear acquisition into a serialized unit', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $acquisition = Acquisition::factory()->create(['branch_id' => $branch->id, 'imei' => '490154203237518']);
    $product = Product::factory()->handset()->create();

    $response = $this->withToken($token)->postJson("/api/v1/acquisitions/{$acquisition->ulid}/complete", [
        'product_ulid' => $product->ulid,
        'condition' => 'secondhand',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.resulting_serialized_unit.imei', '490154203237518')
        ->assertJsonPath('data.resulting_serialized_unit.status', 'in_stock');

    $this->withToken($token)->postJson("/api/v1/acquisitions/{$acquisition->ulid}/complete", [
        'product_ulid' => $product->ulid,
        'condition' => 'secondhand',
    ])->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATUS_TRANSITION');
});
