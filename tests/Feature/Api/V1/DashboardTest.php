<?php

use App\Models\Branch;
use App\Models\RepairTicket;
use App\Models\Sale;

it('gives a cashier their own branch only, without financials', function () {
    $home = Branch::factory()->create(['code' => 'HOME']);
    $other = Branch::factory()->create(['code' => 'OTHR']);

    Sale::factory()->count(2)->create(['branch_id' => $home->id, 'status' => 'completed']);
    Sale::factory()->count(5)->create(['branch_id' => $other->id, 'status' => 'completed']);

    [, $token] = userWithRole('cashier', $home);

    $response = $this->withToken($token)->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.scope', 'branch')
        ->assertJsonPath('data.totals.sales.count_today', 2);

    // The limited dashboard: activity counts, never the shop's economics.
    expect($response->json('data.totals.inventory'))->not->toHaveKey('stock_value')
        ->and($response->json('data'))->not->toHaveKey('branches');
});

it('gives an owner stock value and a per-branch breakdown across all branches', function () {
    $home = Branch::factory()->create(['code' => 'HOME']);
    $other = Branch::factory()->create(['code' => 'OTHR']);

    Sale::factory()->count(2)->create(['branch_id' => $home->id, 'status' => 'completed']);
    Sale::factory()->count(5)->create(['branch_id' => $other->id, 'status' => 'completed']);

    [, $token] = userWithRole('owner', $home);

    $response = $this->withToken($token)->getJson('/api/v1/dashboard?branch=all')
        ->assertOk()
        ->assertJsonPath('data.scope', 'all_branches')
        ->assertJsonPath('data.totals.sales.count_today', 7);

    expect($response->json('data.totals.inventory'))->toHaveKey('stock_value');

    // Factories create their own branches, so assert on the two this test
    // owns rather than the total count of rows in the table.
    $byCode = collect($response->json('data.branches'))->keyBy('code');

    expect($byCode)->toHaveKeys(['HOME', 'OTHR'])
        ->and($byCode['HOME']['metrics']['sales']['count_today'])->toBe(2)
        ->and($byCode['OTHR']['metrics']['sales']['count_today'])->toBe(5);
});

it('excludes voided sales from the dashboard', function () {
    $home = Branch::factory()->create();

    Sale::factory()->count(3)->create(['branch_id' => $home->id, 'status' => 'completed']);
    Sale::factory()->count(2)->create(['branch_id' => $home->id, 'status' => 'voided']);

    [, $token] = userWithRole('owner', $home);

    $this->withToken($token)->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.totals.sales.count_today', 3);
});

it('counts open repairs but not released ones', function () {
    $home = Branch::factory()->create();

    RepairTicket::factory()->count(3)->create(['branch_id' => $home->id, 'status' => 'in_repair']);
    RepairTicket::factory()->count(2)->create(['branch_id' => $home->id, 'status' => 'ready_for_pickup']);
    RepairTicket::factory()->count(4)->create(['branch_id' => $home->id, 'status' => 'released']);

    [, $token] = userWithRole('owner', $home);

    $this->withToken($token)->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.totals.repairs.open', 5)
        ->assertJsonPath('data.totals.repairs.ready_for_pickup', 2);
});

it('denies the dashboard to a role without reports.view', function () {
    $home = Branch::factory()->create();

    [, $token] = userWithRole('technician', $home);

    $this->withToken($token)->getJson('/api/v1/dashboard')->assertStatus(403);
});
