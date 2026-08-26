<?php

it('returns 401 json when no bearer token is present', function () {
    $this->getJson('/api/v1/auth/tokens')
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'UNAUTHENTICATED')
        ->assertHeader('Content-Type', 'application/json');
});

it('returns 404 json for an unknown route', function () {
    $this->getJson('/api/v1/this-route-does-not-exist')
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'NOT_FOUND');
});

it('returns 405 json for a disallowed method on a known route', function () {
    $this->deleteJson('/api/v1/health')
        ->assertStatus(405)
        ->assertJsonPath('error.code', 'METHOD_NOT_ALLOWED')
        ->assertJsonStructure(['error' => ['code', 'message', 'details']]);
});

it('returns 422 json with per-field details on validation failure', function () {
    $response = $this->postJson('/api/v1/auth/token', []);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonStructure(['error' => ['details']]);

    $fields = collect($response->json('error.details'))->pluck('field');

    expect($fields)->toContain('email', 'password', 'device_name');
});

it('returns 500 json for an unhandled exception, never an HTML page', function () {
    $response = $this->getJson('/api/v1/_test/boom');

    $response->assertStatus(500)
        ->assertJsonPath('error.code', 'INTERNAL_ERROR')
        ->assertHeader('Content-Type', 'application/json');

    expect($response->getContent())->not->toContain('<html');
});
