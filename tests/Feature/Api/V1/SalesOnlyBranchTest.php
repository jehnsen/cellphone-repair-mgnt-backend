<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\RepairTicket;
use App\Support\BranchType;

/**
 * A sales_only branch is a retail counter with no repair bench: POS and
 * inventory work, the whole job-order surface does not — regardless of
 * what the actor's role permits. See ChecksBranchCapabilities.
 */
it('blocks job order creation at a sales-only branch', function () {
    $branch = Branch::factory()->salesOnly()->create();
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);
    $device = CustomerDevice::factory()->create(['customer_id' => $customer->id]);

    [, $token] = userWithRole('cashier', $branch);

    $this->withToken($token)->postJson('/api/v1/tickets', [
        'branch_ulid' => $branch->ulid,
        'customer_ulid' => $customer->ulid,
        'customer_device_ulid' => $device->ulid,
        'reported_problem' => 'Cracked screen',
        'terms_accepted' => true,
    ])->assertStatus(403);

    expect(RepairTicket::withoutGlobalScopes()->count())->toBe(0);
});

it('allows job order creation by the same role at a repair branch', function () {
    $branch = Branch::factory()->create(['type' => BranchType::RepairAndSales]);
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);
    $device = CustomerDevice::factory()->create(['customer_id' => $customer->id]);

    [, $token] = userWithRole('cashier', $branch);

    $this->withToken($token)->postJson('/api/v1/tickets', [
        'branch_ulid' => $branch->ulid,
        'customer_ulid' => $customer->ulid,
        'customer_device_ulid' => $device->ulid,
        'reported_problem' => 'Cracked screen',
        'terms_accepted' => true,
    ])->assertStatus(201);
});

it('blocks moving a ticket across the board at a sales-only branch', function () {
    $branch = Branch::factory()->salesOnly()->create();
    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id, 'status' => 'received']);

    [, $token] = userWithRole('cashier', $branch);

    $this->withToken($token)
        ->postJson("/api/v1/tickets/{$ticket->ulid}/transition", ['to_status' => 'diagnosed'])
        ->assertStatus(403);
});

it('still lets a sales-only branch read existing tickets', function () {
    $branch = Branch::factory()->salesOnly()->create();
    RepairTicket::factory()->create(['branch_id' => $branch->id, 'status' => 'received']);

    [, $token] = userWithRole('cashier', $branch);

    // Reading is not gated on branch type — only creating repair work is.
    $this->withToken($token)->getJson('/api/v1/tickets')->assertOk();
});

it('reports the branch type and repair capability on the branch resource', function () {
    $branch = Branch::factory()->salesOnly()->create();

    [, $token] = userWithRole('owner', $branch);

    $this->withToken($token)
        ->getJson("/api/v1/branches/{$branch->ulid}")
        ->assertOk()
        ->assertJsonPath('data.type', 'sales_only')
        ->assertJsonPath('data.offers_repairs', false);
});

it('defaults a new branch to repair_and_sales', function () {
    $branch = Branch::factory()->create();

    [, $token] = userWithRole('owner', $branch);

    $this->withToken($token)->postJson('/api/v1/branches', [
        'name' => 'New Repair Branch',
        'code' => 'NRB',
    ])->assertStatus(201)
        ->assertJsonPath('data.type', 'repair_and_sales')
        ->assertJsonPath('data.offers_repairs', true);
});
