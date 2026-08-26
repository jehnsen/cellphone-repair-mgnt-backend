<?php

use App\Models\Branch;

it('lets an owner list and create branches', function () {
    [, $token] = userWithRole('owner');

    $this->withToken($token)->getJson('/api/v1/branches')->assertOk();

    $this->withToken($token)->postJson('/api/v1/branches', [
        'name' => 'FixMo Phone Repair — Davao',
        'code' => 'DVO',
    ])->assertStatus(201)
        ->assertJsonPath('data.code', 'DVO')
        ->assertJsonPath('data.name', 'FixMo Phone Repair — Davao');

    expect(Branch::where('code', 'DVO')->exists())->toBeTrue();
});

it('denies branch creation to a cashier', function () {
    [, $token] = userWithRole('cashier');

    $this->withToken($token)->postJson('/api/v1/branches', [
        'name' => 'Should Not Be Created',
        'code' => 'XXX',
    ])->assertStatus(403);
});

it('rejects a duplicate branch code', function () {
    [, $token] = userWithRole('owner');
    Branch::factory()->create(['code' => 'QC']);

    $this->withToken($token)->postJson('/api/v1/branches', [
        'name' => 'Duplicate',
        'code' => 'QC',
    ])->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('has no destroy route for branches', function () {
    [, $token] = userWithRole('owner');
    $branch = Branch::factory()->create();

    $this->withToken($token)->deleteJson("/api/v1/branches/{$branch->ulid}")
        ->assertStatus(405);
});
