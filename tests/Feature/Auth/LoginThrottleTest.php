<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    // phpunit.xml pins CACHE_STORE=array, and that store is a
    // process-lifetime singleton that survives RefreshDatabase's rollback.
    // Every test here shares one source IP (127.0.0.1), so without a flush
    // the per-IP bucket carries hits from earlier tests in this file and
    // trips 429 in the middle of an unrelated assertion.
    Cache::store('array')->flush();
});

it('locks out repeated password guesses against one account', function () {
    $user = User::factory()->create([
        'email' => 'grinder@fixmo.test',
        'password' => bcrypt('secret-password'),
    ]);

    // Budget is 5/min per email+IP.
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'attacker',
        ])->assertStatus(401);
    }

    $this->postJson('/api/v1/auth/token', [
        'email' => $user->email,
        'password' => 'wrong-password',
        'device_name' => 'attacker',
    ])->assertStatus(429)
        ->assertJsonPath('error.code', 'RATE_LIMITED');
});

it('blocks a throttled attacker even once they guess the right password', function () {
    $user = User::factory()->create([
        'email' => 'grinder@fixmo.test',
        'password' => bcrypt('secret-password'),
    ]);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'attacker',
        ])->assertStatus(401);
    }

    $this->postJson('/api/v1/auth/token', [
        'email' => $user->email,
        'password' => 'secret-password',
        'device_name' => 'attacker',
    ])->assertStatus(429);
});

it('clears a user\'s failed-attempt budget once they log in successfully', function () {
    // A cashier who fat-fingers their password twice then gets it right
    // must not spend the rest of the minute throttled.
    $user = User::factory()->create([
        'email' => 'grinder@fixmo.test',
        'password' => bcrypt('secret-password'),
    ]);

    foreach (range(1, 3) as $attempt) {
        $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'pos-terminal-1',
        ])->assertStatus(401);
    }

    $this->postJson('/api/v1/auth/token', [
        'email' => $user->email,
        'password' => 'secret-password',
        'device_name' => 'pos-terminal-1',
    ])->assertStatus(201);

    // Budget is back to a full 5, not the 2 that were left.
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'pos-terminal-1',
        ])->assertStatus(401);
    }
});

it('caps password spraying across many accounts from one IP', function () {
    // The per-email bucket alone wouldn't catch this — one guess each
    // against 20 different accounts never trips a 5/email limit.
    foreach (range(1, 20) as $n) {
        $this->postJson('/api/v1/auth/token', [
            'email' => "victim{$n}@fixmo.test",
            'password' => 'Password123',
            'device_name' => 'attacker',
        ])->assertStatus(401);
    }

    $this->postJson('/api/v1/auth/token', [
        'email' => 'victim21@fixmo.test',
        'password' => 'Password123',
        'device_name' => 'attacker',
    ])->assertStatus(429);
});
