<?php

use App\Models\User;

it('issues a bearer token for valid credentials', function () {
    $user = User::factory()->create(['password' => bcrypt('secret-password')]);

    $this->postJson('/api/v1/auth/token', [
        'email' => $user->email,
        'password' => 'secret-password',
        'device_name' => 'pos-terminal-1',
    ])->assertStatus(201)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonStructure(['data' => ['token', 'token_type']]);
});

it('rejects invalid credentials without leaking which field was wrong', function () {
    $user = User::factory()->create(['password' => bcrypt('secret-password')]);

    $this->postJson('/api/v1/auth/token', [
        'email' => $user->email,
        'password' => 'wrong-password',
        'device_name' => 'pos-terminal-1',
    ])->assertStatus(401)
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');
});

it('lets an authenticated device list and revoke its own tokens', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device-a')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/auth/tokens')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->withToken($token)
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    expect($user->tokens()->count())->toBe(0);
});
