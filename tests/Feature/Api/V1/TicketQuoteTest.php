<?php

use App\Models\Branch;
use App\Models\RepairTicket;
use App\Models\TicketQuote;

it('sending a quote from diagnosed auto-advances the ticket to awaiting_approval', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id, 'status' => 'diagnosed']);

    $response = $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/quotes", [
        'quoted_amount' => 1200,
        'channel' => 'sms',
    ]);

    $response->assertStatus(201)->assertJsonPath('data.quoted_amount', '1200.00');
    expect($ticket->fresh()->status)->toBe('awaiting_approval');
});

it('approving a quote locks the approved amount and advances the ticket to in_repair', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $ticket = RepairTicket::factory()->create([
        'branch_id' => $branch->id,
        'status' => 'awaiting_approval',
        'downpayment' => 200,
    ]);
    $quote = TicketQuote::factory()->create([
        'repair_ticket_id' => $ticket->id,
        'quoted_amount' => 1000,
    ]);

    $response = $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/quotes/{$quote->ulid}/respond", [
        'decision' => 'approved',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.quote.decision', 'approved')
        ->assertJsonPath('data.ticket.status', 'in_repair')
        ->assertJsonPath('data.ticket.approved_amount', '1000.00')
        ->assertJsonPath('data.ticket.balance', '800.00');
});

it('declining a quote returns the device as-is', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id, 'status' => 'awaiting_approval']);
    $quote = TicketQuote::factory()->create(['repair_ticket_id' => $ticket->id]);

    $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/quotes/{$quote->ulid}/respond", [
        'decision' => 'declined',
    ])->assertOk()->assertJsonPath('data.ticket.status', 'returned_as_is');
});

it('404s when responding to a quote that belongs to a different ticket', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $ticketA = RepairTicket::factory()->create(['branch_id' => $branch->id]);
    $ticketB = RepairTicket::factory()->create(['branch_id' => $branch->id]);
    $quote = TicketQuote::factory()->create(['repair_ticket_id' => $ticketB->id]);

    $this->withToken($token)
        ->postJson("/api/v1/tickets/{$ticketA->ulid}/quotes/{$quote->ulid}/respond", ['decision' => 'approved'])
        ->assertStatus(404);
});
