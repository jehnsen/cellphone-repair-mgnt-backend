<?php

use App\Models\Branch;
use App\Models\RepairTicket;
use App\Models\Sale;
use App\Models\User;

/**
 * Cross-branch visibility: the owner may widen scope explicitly, everyone
 * else is pinned to their own branch. See App\Support\BranchContext.
 *
 * Note the Sanctum gotcha in CLAUDE.md — one authenticated actor per test.
 */
it('scopes a cashier to their own branch by default', function () {
    $home = Branch::factory()->create(['code' => 'HOME']);
    $other = Branch::factory()->create(['code' => 'OTHR']);

    Sale::factory()->count(2)->create(['branch_id' => $home->id, 'status' => 'completed']);
    Sale::factory()->count(5)->create(['branch_id' => $other->id, 'status' => 'completed']);

    [, $token] = userWithRole('cashier', $home);

    $response = $this->withToken($token)->getJson('/api/v1/sales')->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

it('denies a cashier who asks for another branch', function () {
    $home = Branch::factory()->create(['code' => 'HOME']);
    $other = Branch::factory()->create(['code' => 'OTHR']);

    [, $token] = userWithRole('cashier', $home);

    $this->withToken($token)
        ->getJson("/api/v1/sales?branch={$other->ulid}")
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

it('denies a cashier who asks for all branches', function () {
    $home = Branch::factory()->create(['code' => 'HOME']);

    [, $token] = userWithRole('cashier', $home);

    $this->withToken($token)
        ->getJson('/api/v1/sales?branch=all')
        ->assertStatus(403);
});

it('still scopes an owner to their own branch when they do not ask to widen', function () {
    $home = Branch::factory()->create(['code' => 'HOME']);
    $other = Branch::factory()->create(['code' => 'OTHR']);

    Sale::factory()->count(2)->create(['branch_id' => $home->id, 'status' => 'completed']);
    Sale::factory()->count(5)->create(['branch_id' => $other->id, 'status' => 'completed']);

    [, $token] = userWithRole('owner', $home);

    $response = $this->withToken($token)->getJson('/api/v1/sales')->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

it('lets an owner span every branch with branch=all', function () {
    $home = Branch::factory()->create(['code' => 'HOME']);
    $other = Branch::factory()->create(['code' => 'OTHR']);

    Sale::factory()->count(2)->create(['branch_id' => $home->id, 'status' => 'completed']);
    Sale::factory()->count(5)->create(['branch_id' => $other->id, 'status' => 'completed']);

    [, $token] = userWithRole('owner', $home);

    $response = $this->withToken($token)->getJson('/api/v1/sales?branch=all')->assertOk();

    expect($response->json('data'))->toHaveCount(7);
});

it('lets an owner scope to one specific other branch by ulid', function () {
    $home = Branch::factory()->create(['code' => 'HOME']);
    $other = Branch::factory()->create(['code' => 'OTHR']);

    Sale::factory()->count(2)->create(['branch_id' => $home->id, 'status' => 'completed']);
    Sale::factory()->count(5)->create(['branch_id' => $other->id, 'status' => 'completed']);

    [, $token] = userWithRole('owner', $home);

    $response = $this->withToken($token)->getJson("/api/v1/sales?branch={$other->ulid}")->assertOk();

    expect($response->json('data'))->toHaveCount(5);
});

it('404s an unknown branch identifier for a privileged caller', function () {
    $home = Branch::factory()->create(['code' => 'HOME']);

    [, $token] = userWithRole('owner', $home);

    $this->withToken($token)
        ->getJson('/api/v1/sales?branch=01JNOTAREALULIDVALUE000000')
        ->assertStatus(404);
});

it('hides another branch ticket from a cashier addressing it directly by ulid', function () {
    $home = Branch::factory()->create(['code' => 'HOME']);
    $other = Branch::factory()->create(['code' => 'OTHR']);

    $foreign = RepairTicket::factory()->create(['branch_id' => $other->id]);

    [, $token] = userWithRole('cashier', $home);

    // 404, not 403 — BranchScope removes the row, which is the right
    // answer: it doesn't confirm the ticket exists somewhere else.
    $this->withToken($token)
        ->getJson("/api/v1/tickets/{$foreign->ulid}")
        ->assertStatus(404);
});

it('gives only the owner the branches.view_all permission', function () {
    $branch = Branch::factory()->create();

    [$owner] = userWithRole('owner', $branch);

    expect($owner->can('branches.view_all'))->toBeTrue();

    foreach (['manager', 'cashier', 'technician'] as $role) {
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole($role);

        expect($user->can('branches.view_all'))->toBeFalse();
    }
});
