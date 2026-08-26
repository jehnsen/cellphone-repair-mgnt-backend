<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\DeviceModel;

// A well-known valid Luhn-checksum IMEI used across IMEI-validation examples.
const VALID_TEST_IMEI = '490154203237518';

it('creates a customer device with a valid IMEI', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('cashier', $branch);
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);
    $model = DeviceModel::factory()->create();

    $response = $this->withToken($token)->postJson("/api/v1/customers/{$customer->ulid}/devices", [
        'device_model_ulid' => $model->ulid,
        'imei' => VALID_TEST_IMEI,
    ]);

    $response->assertStatus(201)->assertJsonPath('data.imei', VALID_TEST_IMEI);
});

it('rejects an IMEI that fails the Luhn checksum', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('cashier', $branch);
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);

    $this->withToken($token)->postJson("/api/v1/customers/{$customer->ulid}/devices", [
        'imei' => '490154203237519',
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('404s when a device is looked up under the wrong customer', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('cashier', $branch);
    $customerA = Customer::factory()->create(['branch_id' => $branch->id]);
    $customerB = Customer::factory()->create(['branch_id' => $branch->id]);
    $device = CustomerDevice::factory()->create(['customer_id' => $customerA->id]);

    $this->withToken($token)
        ->getJson("/api/v1/customers/{$customerB->ulid}/devices/{$device->ulid}")
        ->assertStatus(404);
});

it('finds device history by IMEI across different customers who owned it', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('cashier', $branch);
    $firstOwner = Customer::factory()->create(['branch_id' => $branch->id]);
    $secondOwner = Customer::factory()->create(['branch_id' => $branch->id]);

    CustomerDevice::factory()->create([
        'customer_id' => $firstOwner->id,
        'imei_normalized' => VALID_TEST_IMEI,
    ]);
    CustomerDevice::factory()->create([
        'customer_id' => $secondOwner->id,
        'imei_normalized' => VALID_TEST_IMEI,
    ]);

    $response = $this->withToken($token)->getJson('/api/v1/devices/by-imei/'.VALID_TEST_IMEI);

    $response->assertOk()->assertJsonCount(2, 'data');

    $customerUlids = collect($response->json('data'))->pluck('customer.ulid');
    expect($customerUlids)->toContain($firstOwner->ulid, $secondOwner->ulid);
});

it('finds device history even when the previous owner was at a different branch', function () {
    // The differentiator this endpoint exists for: a repeat repair is
    // recognized even when the customer's earlier visit was at another
    // branch — BranchScope must not hide that customer's details here.
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    [, $token] = userWithRole('cashier', $branchA);
    $otherBranchOwner = Customer::factory()->create(['branch_id' => $branchB->id]);

    CustomerDevice::factory()->create([
        'customer_id' => $otherBranchOwner->id,
        'imei_normalized' => VALID_TEST_IMEI,
    ]);

    $response = $this->withToken($token)->getJson('/api/v1/devices/by-imei/'.VALID_TEST_IMEI);

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.customer.ulid', $otherBranchOwner->ulid);
});
