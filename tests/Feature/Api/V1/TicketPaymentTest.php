<?php

use App\Models\Branch;
use App\Models\RepairTicket;

it('records a payment against a ticket and reduces its balance', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $ticket = RepairTicket::factory()->create([
        'branch_id' => $branch->id,
        'approved_amount' => 1500,
        'downpayment' => 500,
        'balance' => 1000,
    ]);

    $response = $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/payments", [
        'method' => 'cash',
        'amount' => 600,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.payment.amount', '600.00')
        ->assertJsonPath('data.payment.change_given', '0.00')
        ->assertJsonPath('data.ticket.balance', '400.00');
});

it('rejects a ticket payment that would overpay the balance', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $ticket = RepairTicket::factory()->create([
        'branch_id' => $branch->id,
        'approved_amount' => 1000,
        'downpayment' => 0,
        'balance' => 1000,
    ]);

    $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/payments", [
        'method' => 'cash',
        'amount' => 1500,
    ])->assertStatus(409)->assertJsonPath('error.code', 'PAYMENT_SUM_MISMATCH');
});

it('blocks release until the balance is fully paid', function () {
    // The only remaining release guard — IMEI verification no longer
    // blocks release (see ImeiVerificationTest), so this needs no scan
    // precondition to isolate it.
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $ticket = RepairTicket::factory()->create([
        'branch_id' => $branch->id,
        'status' => 'ready_for_pickup',
        'approved_amount' => 500,
        'downpayment' => 0,
        'balance' => 500,
    ]);

    $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/transition", [
        'to_status' => 'released',
    ])->assertStatus(409)->assertJsonPath('error.code', 'PAYMENT_SUM_MISMATCH');

    $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/payments", [
        'method' => 'cash',
        'amount' => 500,
    ])->assertStatus(201)->assertJsonPath('data.ticket.balance', '0.00');

    $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/transition", [
        'to_status' => 'released',
    ])->assertOk()->assertJsonPath('data.status', 'released');
});
