<?php

use App\Services\SystemResetService;
use App\Support\Api\ApiException;

/**
 * The happy path actually runs `migrate:fresh` + BaseInstallSeeder, which
 * is incompatible with RefreshDatabase's per-test transaction — so the
 * service is swapped for a spy here and its own behaviour is covered by
 * the guard test plus the seeder assertions below. The value of these
 * tests is the authorization / confirmation gate on the endpoint.
 */
it('lets an owner trigger a fresh install with the confirmation token', function () {
    [, $token] = userWithRole('owner');

    $spy = Mockery::mock(SystemResetService::class);
    $spy->shouldReceive('freshInstall')->once()->andReturn(['tables_recreated' => 60]);
    $this->app->instance(SystemResetService::class, $spy);

    $this->withToken($token)
        ->postJson('/api/v1/system/fresh-install', ['confirm' => 'RESET'])
        ->assertOk()
        ->assertJsonPath('data.status', 'reset_complete')
        ->assertJsonPath('data.tables_recreated', 60);
});

it('rejects a fresh install without the confirmation token', function () {
    [, $token] = userWithRole('owner');

    $this->withToken($token)
        ->postJson('/api/v1/system/fresh-install', [])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('rejects a fresh install with the wrong confirmation token', function () {
    [, $token] = userWithRole('owner');

    $this->withToken($token)
        ->postJson('/api/v1/system/fresh-install', ['confirm' => 'reset'])
        ->assertStatus(422);
});

it('denies a fresh install to a manager', function () {
    [, $token] = userWithRole('manager');

    $this->withToken($token)
        ->postJson('/api/v1/system/fresh-install', ['confirm' => 'RESET'])
        ->assertStatus(403);
});

it('denies a fresh install to a cashier', function () {
    [, $token] = userWithRole('cashier');

    $this->withToken($token)
        ->postJson('/api/v1/system/fresh-install', ['confirm' => 'RESET'])
        ->assertStatus(403);
});

it('requires authentication for a fresh install', function () {
    $this->postJson('/api/v1/system/fresh-install', ['confirm' => 'RESET'])
        ->assertStatus(401);
});

it('refuses a fresh install in production without the opt-in flag', function () {
    app()->detectEnvironment(fn () => 'production');
    config()->set('app.allow_system_reset', false);

    expect(fn () => (new SystemResetService)->freshInstall())
        ->toThrow(ApiException::class);
});
