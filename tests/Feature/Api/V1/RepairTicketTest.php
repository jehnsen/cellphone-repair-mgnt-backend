<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\RepairTicket;

function createTicketFixtures(Branch $branch): array
{
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);
    $device = CustomerDevice::factory()->create(['customer_id' => $customer->id]);

    return [$customer, $device];
}

it('creates a repair ticket by resolving ULIDs and snapshotting device details', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    [$customer, $device] = createTicketFixtures($branch);

    $response = $this->withToken($token)->postJson('/api/v1/tickets', [
        'branch_ulid' => $branch->ulid,
        'customer_ulid' => $customer->ulid,
        'customer_device_ulid' => $device->ulid,
        'reported_problem' => 'Screen cracked.',
        'problem_tags' => ['screen'],
        'unlock_method' => 'none',
        'estimated_cost' => 1500,
        'downpayment' => 500,
        'terms_accepted' => true,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'received')
        ->assertJsonPath('data.balance', '1000.00')
        ->assertJsonPath('data.customer.ulid', $customer->ulid);

    expect($response->json('data.ulid'))->not->toBe((string) RepairTicket::first()->id);
    expect($response->json('data'))->not->toHaveKey('id');
});

it('creates a repair ticket with only the required fields', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    [$customer, $device] = createTicketFixtures($branch);

    // No reported_problem, no unlock method/value, no downpayment — a
    // customer who declines to say more than "it's broken" at intake.
    $this->withToken($token)->postJson('/api/v1/tickets', [
        'branch_ulid' => $branch->ulid,
        'customer_ulid' => $customer->ulid,
        'customer_device_ulid' => $device->ulid,
        'terms_accepted' => true,
    ])->assertStatus(201)->assertJsonPath('data.status', 'received');
});

it('treats an unlock method with a blank value as no unlock info', function () {
    $branch = Branch::factory()->create();
    [$manager, $token] = userWithRole('manager', $branch);
    [$customer, $device] = createTicketFixtures($branch);

    // The intake form defaults the method to 'pin' even when the tech
    // leaves the value blank — this must not 422.
    $response = $this->withToken($token)->postJson('/api/v1/tickets', [
        'branch_ulid' => $branch->ulid,
        'customer_ulid' => $customer->ulid,
        'customer_device_ulid' => $device->ulid,
        'unlock_method' => 'pin',
        'unlock_value' => null,
        'terms_accepted' => true,
    ])->assertStatus(201);

    expect(RepairTicket::where('ulid', $response->json('data.ulid'))->sole()->unlock_method)->toBe('none');
});

it('still requires the unlock value when a method is paired with a real one later', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    [$customer, $device] = createTicketFixtures($branch);

    // Sending only unlock_value with no method still stores both.
    $this->withToken($token)->postJson('/api/v1/tickets', [
        'branch_ulid' => $branch->ulid,
        'customer_ulid' => $customer->ulid,
        'customer_device_ulid' => $device->ulid,
        'unlock_method' => 'pin',
        'unlock_value' => '1234',
        'terms_accepted' => true,
    ])->assertStatus(201)->assertJsonPath('data.status', 'received');
});

it('rejects ticket creation without terms accepted', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    [$customer, $device] = createTicketFixtures($branch);

    $this->withToken($token)->postJson('/api/v1/tickets', [
        'branch_ulid' => $branch->ulid,
        'customer_ulid' => $customer->ulid,
        'customer_device_ulid' => $device->ulid,
        'terms_accepted' => false,
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('forbids a cashier from creating a ticket', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('cashier', $branch);
    [$customer, $device] = createTicketFixtures($branch);

    $this->withToken($token)->postJson('/api/v1/tickets', [
        'branch_ulid' => $branch->ulid,
        'customer_ulid' => $customer->ulid,
        'customer_device_ulid' => $device->ulid,
        'terms_accepted' => true,
    ])->assertStatus(403);
});

it('allows a legal status transition and records a timeline event', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id, 'status' => 'received']);

    $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/transition", [
        'to_status' => 'diagnosed',
    ])->assertOk()->assertJsonPath('data.status', 'diagnosed');

    $events = $this->withToken($token)->getJson("/api/v1/tickets/{$ticket->ulid}/events")->assertOk();
    $types = collect($events->json('data'))->pluck('event_type');
    expect($types)->toContain('status_changed');
});

it('rejects an illegal status transition', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id, 'status' => 'received']);

    $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/transition", [
        'to_status' => 'released',
    ])->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATUS_TRANSITION');
});

it('requires the release permission to transition a ticket to released', function () {
    $branch = Branch::factory()->create();
    [, $technicianToken] = userWithRole('technician', $branch);
    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id, 'status' => 'ready_for_pickup']);

    $this->withToken($technicianToken)->postJson("/api/v1/tickets/{$ticket->ulid}/transition", [
        'to_status' => 'released',
    ])->assertStatus(403);
});

it('blocks edits to a released ticket', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id, 'status' => 'released']);

    $this->withToken($token)->patchJson("/api/v1/tickets/{$ticket->ulid}", [
        'reported_problem' => 'Updated.',
    ])->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATUS_TRANSITION');
});

it('hides margin and unlock fields from a role without the matching permission', function () {
    $branch = Branch::factory()->create();
    [, $cashierToken] = userWithRole('cashier', $branch);
    $ticket = RepairTicket::factory()->create([
        'branch_id' => $branch->id,
        'unlock_method' => 'pin',
        'unlock_value' => '1234',
    ]);

    $response = $this->withToken($cashierToken)->getJson("/api/v1/tickets/{$ticket->ulid}")->assertOk();

    expect($response->json('data'))->not->toHaveKey('unlock_value');
    expect($response->json('data'))->not->toHaveKey('margin');
});

it('shows unlock fields to a role with tickets.update permission', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $ticket = RepairTicket::factory()->create([
        'branch_id' => $branch->id,
        'unlock_method' => 'pin',
        'unlock_value' => '1234',
    ]);

    $this->withToken($token)->getJson("/api/v1/tickets/{$ticket->ulid}")
        ->assertOk()
        ->assertJsonPath('data.unlock_value', '1234');
});
