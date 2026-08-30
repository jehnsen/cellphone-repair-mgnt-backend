<?php

use App\Models\User;

it('refuses to issue a token to a deactivated user', function () {
    $user = User::factory()->inactive()->create(['password' => bcrypt('secret-password')]);

    $this->postJson('/api/v1/auth/token', [
        'email' => $user->email,
        'password' => 'secret-password',
        'device_name' => 'pos-terminal-1',
    ])->assertStatus(403)
        ->assertJsonPath('error.code', 'ACCOUNT_DISABLED');
});

it('reports bad credentials rather than account status for a deactivated user with the wrong password', function () {
    // The status check must sit behind the password check, or the endpoint
    // becomes an oracle for which emails are real accounts.
    $user = User::factory()->inactive()->create(['password' => bcrypt('secret-password')]);

    $this->postJson('/api/v1/auth/token', [
        'email' => $user->email,
        'password' => 'wrong-password',
        'device_name' => 'pos-terminal-1',
    ])->assertStatus(401)
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');
});

it('rejects an already-issued token once the user is deactivated', function () {
    // The half that matters most: sanctum tokens never expire on their own
    // (config/sanctum.php sets 'expiration' => null), so without this a
    // deactivated employee keeps every token they were ever issued.
    //
    // Only ONE authenticated request is made here, deliberately: the sanctum
    // guard caches the resolved user for the rest of the test process, so a
    // warm-up call as the same token would make the second call pass on the
    // cached user and never re-run the token check. See CLAUDE.md.
    [$user, $token] = userWithRole('technician');

    $user->forceFill(['is_active' => false])->save();

    $this->withToken($token)
        ->getJson('/api/v1/auth/tokens')
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');
});

it('accepts an already-issued token while the user is still active', function () {
    // Control for the test above — proves the 401 there comes from the
    // is_active check and not from something wrong with the token itself.
    [, $token] = userWithRole('technician');

    $this->withToken($token)->getJson('/api/v1/auth/tokens')->assertOk();
});

it('purges issued tokens when a user is deactivated through the API', function () {
    [$owner, $ownerToken] = userWithRole('owner');

    $target = User::factory()->create(['branch_id' => $owner->branch_id]);
    $target->assignRole('technician');
    $target->createToken('device-a');
    $target->createToken('device-b');

    expect($target->tokens()->count())->toBe(2);

    $this->withToken($ownerToken)
        ->patchJson("/api/v1/users/{$target->ulid}", ['is_active' => false])
        ->assertOk();

    expect($target->fresh()->tokens()->count())->toBe(0);
});

it('purges issued tokens when a user is deleted', function () {
    [$owner, $ownerToken] = userWithRole('owner');

    $target = User::factory()->create(['branch_id' => $owner->branch_id]);
    $target->assignRole('technician');
    $target->createToken('device-a');

    $this->withToken($ownerToken)
        ->deleteJson("/api/v1/users/{$target->ulid}")
        ->assertNoContent();

    expect($target->tokens()->count())->toBe(0);
});

it('leaves an active user\'s tokens alone on an unrelated update', function () {
    [$owner, $ownerToken] = userWithRole('owner');

    $target = User::factory()->create(['branch_id' => $owner->branch_id]);
    $target->assignRole('technician');
    $target->createToken('device-a');

    $this->withToken($ownerToken)
        ->patchJson("/api/v1/users/{$target->ulid}", ['name' => 'Renamed Technician'])
        ->assertOk();

    expect($target->fresh()->tokens()->count())->toBe(1);
});
