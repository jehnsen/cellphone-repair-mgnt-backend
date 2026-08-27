<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\RepairTicket;
use App\Models\VerificationToken;

it('exposes the verification token on the ticket so staff can build the public link', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);
    $device = CustomerDevice::factory()->create(['customer_id' => $customer->id]);

    $created = $this->withToken($token)->postJson('/api/v1/tickets', [
        'branch_ulid' => $branch->ulid,
        'customer_ulid' => $customer->ulid,
        'customer_device_ulid' => $device->ulid,
        'unlock_method' => 'none',
        'terms_accepted' => true,
    ])->assertStatus(201);

    $verificationToken = $created->json('data.verification_token');
    expect($verificationToken)->toBeString()->not->toBeEmpty();

    $this->getJson("/api/v1/public/verify/{$verificationToken}")
        ->assertOk()
        ->assertJsonPath('data.ticket_number', $created->json('data.ticket_number'));
});

it('returns a redacted proof for a valid token, unauthenticated', function () {
    $ticket = RepairTicket::factory()->create();
    $verification = VerificationToken::factory()->create(['repair_ticket_id' => $ticket->id]);

    $response = $this->getJson("/api/v1/public/verify/{$verification->token}");

    $response->assertOk()
        ->assertJsonPath('data.ticket_number', $ticket->ticket_number)
        ->assertJsonPath('data.status', $ticket->status);

    expect($response->json('data'))
        ->not->toHaveKey('claim_code')
        ->not->toHaveKey('customer')
        ->not->toHaveKey('unlock_value');
});

it('404s for an unknown token', function () {
    $this->getJson('/api/v1/public/verify/'.str_repeat('x', 32))->assertStatus(404);
});

it('404s for a revoked token', function () {
    $ticket = RepairTicket::factory()->create();
    $verification = VerificationToken::factory()->create([
        'repair_ticket_id' => $ticket->id,
        'revoked_at' => now(),
    ]);

    $this->getJson("/api/v1/public/verify/{$verification->token}")->assertStatus(404);
});
