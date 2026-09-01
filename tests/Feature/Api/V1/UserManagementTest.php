<?php

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

/**
 * Staff administration: only an owner creates accounts, an account can
 * never outrank its creator, and the owner can administer staff at a
 * branch other than their own.
 */
it('lets an owner create a cashier at another branch', function () {
    $home = Branch::factory()->create(['code' => 'HOME']);
    $retail = Branch::factory()->salesOnly()->create(['code' => 'RTL']);

    [, $token] = userWithRole('owner', $home);

    $response = $this->withToken($token)->postJson('/api/v1/users', [
        'branch_ulid' => $retail->ulid,
        'employee_code' => 'EMP-5001',
        'name' => 'Retail Cashier',
        'email' => 'retail.cashier@example.test',
        'password' => 'secret-password-1',
        'role' => 'cashier',
    ])->assertStatus(201)
        ->assertJsonPath('data.branch.code', 'RTL');

    $created = User::withoutGlobalScopes()->where('email', 'retail.cashier@example.test')->sole();

    expect($created->branch_id)->toBe($retail->id)
        ->and($created->hasRole('cashier'))->toBeTrue()
        ->and($response->json('data'))->not->toHaveKey('password');

    // The 201 body must report the account as active. The DB column
    // defaults to true, but a model default is what makes the *response*
    // say so — without it this came back null and a client would read the
    // new account as disabled.
    expect($response->json('data.is_active'))->toBeTrue();
});

it('lets an owner read back a user at another branch with branch=all', function () {
    $home = Branch::factory()->create(['code' => 'HOME']);
    $retail = Branch::factory()->create(['code' => 'RTL']);

    // userWithRole seeds the role matrix, so take the owner token first —
    // assignRole below needs the roles to already exist.
    [, $token] = userWithRole('owner', $home);

    $cashier = User::factory()->create(['branch_id' => $retail->id]);
    $cashier->assignRole('cashier');

    // Route-model binding runs under BranchScope, so this only works
    // because ResolveBranchContext is registered ahead of
    // SubstituteBindings — see bootstrap/app.php.
    $this->withToken($token)
        ->getJson("/api/v1/users/{$cashier->ulid}?branch=all")
        ->assertOk()
        ->assertJsonPath('data.ulid', $cashier->ulid);
});

it('hides a user at another branch without an explicit branch scope', function () {
    $home = Branch::factory()->create(['code' => 'HOME']);
    $retail = Branch::factory()->create(['code' => 'RTL']);

    $cashier = User::factory()->create(['branch_id' => $retail->id]);

    [, $token] = userWithRole('owner', $home);

    // The default is unchanged: own branch only, unless asked otherwise.
    $this->withToken($token)
        ->getJson("/api/v1/users/{$cashier->ulid}")
        ->assertStatus(404);
});

it('denies user creation to a manager', function () {
    $branch = Branch::factory()->create();

    [, $token] = userWithRole('manager', $branch);

    $this->withToken($token)->postJson('/api/v1/users', [
        'branch_ulid' => $branch->ulid,
        'employee_code' => 'EMP-5002',
        'name' => 'Nope',
        'email' => 'nope@example.test',
        'password' => 'secret-password-1',
        'role' => 'cashier',
    ])->assertStatus(403);

    expect(User::withoutGlobalScopes()->where('email', 'nope@example.test')->exists())->toBeFalse();
});

it('denies user creation to a cashier', function () {
    $branch = Branch::factory()->create();

    [, $token] = userWithRole('cashier', $branch);

    $this->withToken($token)->postJson('/api/v1/users', [
        'branch_ulid' => $branch->ulid,
        'employee_code' => 'EMP-5003',
        'name' => 'Nope',
        'email' => 'nope2@example.test',
        'password' => 'secret-password-1',
        'role' => 'cashier',
    ])->assertStatus(403);
});

it('lets an owner create another owner', function () {
    $branch = Branch::factory()->create();

    [, $token] = userWithRole('owner', $branch);

    $this->withToken($token)->postJson('/api/v1/users', [
        'branch_ulid' => $branch->ulid,
        'employee_code' => 'EMP-5004',
        'name' => 'Second Owner',
        'email' => 'second.owner@example.test',
        'password' => 'secret-password-1',
        'role' => 'owner',
    ])->assertStatus(201);
});

it('stops a non-owner user manager from minting an owner account', function () {
    $branch = Branch::factory()->create();

    // A manager who has been granted users.manage directly — the role
    // matrix could move that permission at any time. Creating staff is
    // then fine; creating an *owner* still is not.
    test()->seed(RoleAndPermissionSeeder::class);
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('manager');
    $manager->givePermissionTo('users.manage');
    $token = $manager->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/users', [
        'branch_ulid' => $branch->ulid,
        'employee_code' => 'EMP-5005',
        'name' => 'Escalated',
        'email' => 'escalated@example.test',
        'password' => 'secret-password-1',
        'role' => 'owner',
    ])->assertStatus(403);

    expect(User::withoutGlobalScopes()->where('email', 'escalated@example.test')->exists())->toBeFalse();

    // ...but the same actor may still create an ordinary cashier.
    $this->withToken($token)->postJson('/api/v1/users', [
        'branch_ulid' => $branch->ulid,
        'employee_code' => 'EMP-5006',
        'name' => 'Ordinary Cashier',
        'email' => 'ordinary@example.test',
        'password' => 'secret-password-1',
        'role' => 'cashier',
    ])->assertStatus(201);
});

it('stops a non-owner user manager from promoting someone to owner', function () {
    $branch = Branch::factory()->create();

    test()->seed(RoleAndPermissionSeeder::class);
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('manager');
    $manager->givePermissionTo('users.manage');
    $token = $manager->createToken('test')->plainTextToken;

    $target = User::factory()->create(['branch_id' => $branch->id]);
    $target->assignRole('cashier');

    $this->withToken($token)
        ->patchJson("/api/v1/users/{$target->ulid}", ['role' => 'owner'])
        ->assertStatus(403);

    expect($target->fresh()->hasRole('owner'))->toBeFalse();
});

it('rejects an unknown role with a validation error, not a 403', function () {
    $branch = Branch::factory()->create();

    [, $token] = userWithRole('owner', $branch);

    $this->withToken($token)->postJson('/api/v1/users', [
        'branch_ulid' => $branch->ulid,
        'employee_code' => 'EMP-5007',
        'name' => 'Bad Role',
        'email' => 'bad.role@example.test',
        'password' => 'secret-password-1',
        'role' => 'superadmin',
    ])->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('rejects a missing role with a validation error, not a 403', function () {
    $branch = Branch::factory()->create();

    [, $token] = userWithRole('owner', $branch);

    // authorize() runs before validation, so a missing role must fall
    // through to the `required` rule rather than read as an escalation.
    $this->withToken($token)->postJson('/api/v1/users', [
        'branch_ulid' => $branch->ulid,
        'employee_code' => 'EMP-5008',
        'name' => 'No Role',
        'email' => 'no.role@example.test',
        'password' => 'secret-password-1',
    ])->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED');
});
