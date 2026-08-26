<?php

use App\Models\User;

it('returns the original response for a repeated idempotency key', function () {
    $user = User::factory()->create(['password' => bcrypt('secret-password')]);

    $payload = [
        'email' => $user->email,
        'password' => 'secret-password',
        'device_name' => 'pos-terminal-1',
    ];

    $first = $this->withHeaders(['Idempotency-Key' => 'replay-key-1'])
        ->postJson('/api/v1/auth/token', $payload);

    $second = $this->withHeaders(['Idempotency-Key' => 'replay-key-1'])
        ->postJson('/api/v1/auth/token', $payload);

    $first->assertStatus(201);
    $second->assertStatus(201);

    // Same cached response returned verbatim — not a second token issued.
    expect($second->json('data.token'))->toBe($first->json('data.token'));
    expect($user->tokens()->count())->toBe(1);
});

it('rejects a repeated idempotency key sent with a different request body', function () {
    $userA = User::factory()->create(['password' => bcrypt('secret-password')]);
    $userB = User::factory()->create(['password' => bcrypt('secret-password')]);

    $this->withHeaders(['Idempotency-Key' => 'conflict-key-1'])
        ->postJson('/api/v1/auth/token', [
            'email' => $userA->email,
            'password' => 'secret-password',
            'device_name' => 'pos-terminal-1',
        ])->assertStatus(201);

    $this->withHeaders(['Idempotency-Key' => 'conflict-key-1'])
        ->postJson('/api/v1/auth/token', [
            'email' => $userB->email,
            'password' => 'secret-password',
            'device_name' => 'pos-terminal-1',
        ])->assertStatus(409)
        ->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
});

it('does not deduplicate requests sent without an idempotency key', function () {
    $user = User::factory()->create(['password' => bcrypt('secret-password')]);

    $payload = [
        'email' => $user->email,
        'password' => 'secret-password',
        'device_name' => 'pos-terminal-1',
    ];

    $this->postJson('/api/v1/auth/token', $payload)->assertStatus(201);
    $this->postJson('/api/v1/auth/token', $payload)->assertStatus(201);

    expect($user->tokens()->count())->toBe(2);
});
