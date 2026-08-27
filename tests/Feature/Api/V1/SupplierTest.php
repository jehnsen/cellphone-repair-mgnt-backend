<?php

use App\Models\Branch;
use App\Models\Supplier;

it('creates a supplier', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);

    $this->withToken($token)->postJson('/api/v1/suppliers', [
        'name' => 'Global Parts Trading',
        'contact_name' => 'Ana Reyes',
        'contact_phone' => '0917 111 2222',
        'contact_email' => 'ana@globalparts.test',
        'terms' => 'Net 30',
    ])->assertStatus(201)->assertJsonPath('data.name', 'Global Parts Trading');
});

it('forbids a cashier from creating a supplier', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('cashier', $branch);

    $this->withToken($token)->postJson('/api/v1/suppliers', [
        'name' => 'Global Parts Trading',
    ])->assertStatus(403);
});

it('allows a cashier to view suppliers', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('cashier', $branch);
    Supplier::factory()->create();

    $this->withToken($token)->getJson('/api/v1/suppliers')->assertOk();
});

it('soft-deletes a supplier', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $supplier = Supplier::factory()->create();

    $this->withToken($token)->deleteJson("/api/v1/suppliers/{$supplier->ulid}")->assertStatus(204);

    expect(Supplier::find($supplier->id))->toBeNull();
    expect(Supplier::withTrashed()->find($supplier->id))->not->toBeNull();
});
