<?php

use App\Models\Branch;
use App\Models\User;

it('lets an owner create a user and resolves branch_ulid to the internal id', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('owner', $branch);

    $response = $this->withToken($token)->postJson('/api/v1/users', [
        'branch_ulid' => $branch->ulid,
        'employee_code' => 'EMP-9001',
        'name' => 'Nico Bautista',
        'email' => 'nico.bautista@fixmo.test',
        'password' => 'password123',
        'role' => 'technician',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.employee_code', 'EMP-9001')
        ->assertJsonPath('data.branch.ulid', $branch->ulid)
        ->assertJsonPath('data.roles.0', 'technician');

    $user = User::where('employee_code', 'EMP-9001')->firstOrFail();
    expect($user->branch_id)->toBe($branch->id);
    expect($user->hasRole('technician'))->toBeTrue();
});

it('never exposes the password hash', function () {
    [$owner, $token] = userWithRole('owner');

    $this->withToken($token)->getJson("/api/v1/users/{$owner->ulid}")
        ->assertOk()
        ->assertJsonMissingPath('data.password');
});

it('denies user management to a technician', function () {
    [, $token] = userWithRole('technician');

    $this->withToken($token)->getJson('/api/v1/users')->assertStatus(403);
});

it('prevents a user from deleting themself', function () {
    [$owner, $token] = userWithRole('owner');

    $this->withToken($token)->deleteJson("/api/v1/users/{$owner->ulid}")
        ->assertStatus(403);
});
