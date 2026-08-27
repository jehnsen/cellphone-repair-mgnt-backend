<?php

use App\Models\Branch;
use App\Models\CustomerDevice;
use App\Models\RepairTicket;

// A well-known valid Luhn-checksum IMEI, same one used across the suite.
const CUSTODY_TEST_IMEI = '490154203237518';
const CUSTODY_OTHER_IMEI = '050935988732661'; // also a valid Luhn checksum, deliberately different

it('records a matching IMEI verification', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $device = CustomerDevice::factory()->create(['imei_normalized' => CUSTODY_TEST_IMEI]);
    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id, 'customer_device_id' => $device->id]);

    $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/imei-verifications", [
        'phase' => 'intake',
        'scanned_imei' => CUSTODY_TEST_IMEI,
    ])->assertStatus(201)
        ->assertJsonPath('data.matches_expected', true)
        ->assertJsonPath('data.scanned_imei', CUSTODY_TEST_IMEI);
});

it('records a mismatched IMEI verification without rejecting the request', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $device = CustomerDevice::factory()->create(['imei_normalized' => CUSTODY_TEST_IMEI]);
    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id, 'customer_device_id' => $device->id]);

    $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/imei-verifications", [
        'phase' => 'pre_repair',
        'scanned_imei' => CUSTODY_OTHER_IMEI,
    ])->assertStatus(201)->assertJsonPath('data.matches_expected', false);
});

it('rejects an invalid IMEI', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id]);

    $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/imei-verifications", [
        'phase' => 'intake',
        'scanned_imei' => '123',
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('forbids a technician from overriding an IMEI mismatch', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('technician', $branch);
    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id]);

    $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/imei-verifications/override", [
        'phase' => 'release',
        'scanned_imei' => CUSTODY_OTHER_IMEI,
        'override_reason' => 'Customer confirmed it is the same device; screen too damaged to reliably scan.',
    ])->assertStatus(403);
});

it('allows a manager to override an IMEI mismatch, logged with a reason', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id]);

    $response = $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/imei-verifications/override", [
        'phase' => 'release',
        'scanned_imei' => CUSTODY_OTHER_IMEI,
        'override_reason' => 'Box mixed up during intake; customer confirmed device by other identifying marks.',
    ])->assertStatus(201)
        ->assertJsonPath('data.override_reason', 'Box mixed up during intake; customer confirmed device by other identifying marks.');

    expect($response->json('data.overridden_by.ulid'))->toBeString();
});

it('blocks releasing a ticket without a release-phase IMEI verification', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id, 'status' => 'ready_for_pickup']);

    $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/transition", [
        'to_status' => 'released',
    ])->assertStatus(409)->assertJsonPath('error.code', 'IMEI_MISMATCH');
});

it('allows releasing a ticket after a matching release-phase IMEI verification', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $device = CustomerDevice::factory()->create(['imei_normalized' => CUSTODY_TEST_IMEI]);
    $ticket = RepairTicket::factory()->create([
        'branch_id' => $branch->id,
        'customer_device_id' => $device->id,
        'status' => 'ready_for_pickup',
    ]);

    $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/imei-verifications", [
        'phase' => 'release',
        'scanned_imei' => CUSTODY_TEST_IMEI,
    ])->assertStatus(201);

    $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/transition", [
        'to_status' => 'released',
    ])->assertOk()->assertJsonPath('data.status', 'released');
});

it('allows releasing a ticket after an owner override despite a mismatch', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id, 'status' => 'ready_for_pickup']);

    $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/imei-verifications/override", [
        'phase' => 'release',
        'scanned_imei' => CUSTODY_OTHER_IMEI,
        'override_reason' => 'Approved by manager on duty; customer identity confirmed via claim code and ID.',
    ])->assertStatus(201);

    $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/transition", [
        'to_status' => 'released',
    ])->assertOk()->assertJsonPath('data.status', 'released');
});
