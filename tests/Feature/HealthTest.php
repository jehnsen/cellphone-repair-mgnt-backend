<?php

it('reports liveness', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('data.status', 'up');
});

it('reports readiness with a per-dependency breakdown', function () {
    $this->getJson('/api/v1/ready')
        ->assertOk()
        ->assertJsonPath('data.status', 'ready')
        ->assertJsonPath('data.checks.database', true);
});
