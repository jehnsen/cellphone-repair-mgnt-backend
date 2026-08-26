<?php

use App\Models\Branch;
use App\Models\Customer;

it('normalizes common PH mobile input formats before storing', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('cashier', $branch);

    $response = $this->withToken($token)->postJson('/api/v1/customers', [
        'branch_ulid' => $branch->ulid,
        'name' => 'Juan Dela Cruz',
        'mobile' => '0917 123 4567',
    ]);

    $response->assertStatus(201)->assertJsonPath('data.mobile', '+639171234567');

    // Bypass BranchScope here on purpose — this check runs after the HTTP
    // call above, and the acting cashier's authenticated context is still
    // live for the rest of the test process, which would otherwise scope
    // this query too (a real behavior of BranchScope, not a bug).
    expect(Customer::withoutGlobalScopes()->where('name', 'Juan Dela Cruz')->first()->mobile)
        ->toBe('+639171234567');
});

it('rejects an invalid PH mobile number', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('cashier', $branch);

    $this->withToken($token)->postJson('/api/v1/customers', [
        'branch_ulid' => $branch->ulid,
        'name' => 'Invalid Mobile',
        'mobile' => '12345',
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('requires a blacklist reason when marking a customer blacklisted', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('cashier', $branch);

    $this->withToken($token)->postJson('/api/v1/customers', [
        'branch_ulid' => $branch->ulid,
        'name' => 'No Reason Given',
        'mobile' => '09171234567',
        'is_blacklisted' => true,
    ])->assertStatus(422);
});
