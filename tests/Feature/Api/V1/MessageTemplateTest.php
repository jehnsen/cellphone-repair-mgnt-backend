<?php

use App\Models\MessageTemplate;

it('lists templates with the merge fields parsed out of the body', function () {
    [, $token] = userWithRole('owner');

    MessageTemplate::factory()->create([
        'channel' => 'sms',
        'event_key' => 'ticket.ready_for_pickup',
        'body' => 'Hi {{customer_name}}, JO {{ticket_number}} is ready. {{customer_name}} again.',
    ]);

    $this->withToken($token)->getJson('/api/v1/message-templates')
        ->assertOk()
        ->assertJsonPath('data.0.merge_fields', ['customer_name', 'ticket_number']);
});

it('creates a template for a channel and event key', function () {
    [, $token] = userWithRole('owner');

    $this->withToken($token)->postJson('/api/v1/message-templates', [
        'channel' => 'viber',
        'event_key' => 'quote.sent',
        'body' => 'Hi {{customer_name}}, your quote for JO {{ticket_number}} is {{amount}}.',
    ])->assertStatus(201)
        ->assertJsonPath('data.channel', 'viber')
        ->assertJsonPath('data.event_key', 'quote.sent')
        ->assertJsonPath('data.is_active', true);
});

it('rejects an unknown event key', function () {
    [, $token] = userWithRole('owner');

    $this->withToken($token)->postJson('/api/v1/message-templates', [
        'channel' => 'sms',
        'event_key' => 'ticket.exploded',
        'body' => 'x',
    ])->assertStatus(422)->assertJsonPath('error.details.0.field', 'event_key');
});

it('rejects a duplicate channel + event key pair', function () {
    [, $token] = userWithRole('owner');
    MessageTemplate::factory()->create(['channel' => 'sms', 'event_key' => 'ticket.received']);

    $this->withToken($token)->postJson('/api/v1/message-templates', [
        'channel' => 'sms',
        'event_key' => 'ticket.received',
        'body' => 'dup',
    ])->assertStatus(422);
});

it('allows the same event key on a different channel', function () {
    [, $token] = userWithRole('owner');
    MessageTemplate::factory()->create(['channel' => 'sms', 'event_key' => 'ticket.received']);

    $this->withToken($token)->postJson('/api/v1/message-templates', [
        'channel' => 'viber',
        'event_key' => 'ticket.received',
        'body' => 'ok on viber',
    ])->assertStatus(201);
});

it('updates the body and active flag but not the identity', function () {
    [, $token] = userWithRole('owner');
    $template = MessageTemplate::factory()->create([
        'channel' => 'sms',
        'event_key' => 'ticket.received',
        'is_active' => true,
    ]);

    $this->withToken($token)->patchJson("/api/v1/message-templates/{$template->ulid}", [
        'body' => 'New copy {{customer_name}}.',
        'is_active' => false,
        'channel' => 'viber',      // ignored — not in the update rules
        'event_key' => 'quote.sent',
    ])->assertOk()
        ->assertJsonPath('data.body', 'New copy {{customer_name}}.')
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.channel', 'sms')
        ->assertJsonPath('data.event_key', 'ticket.received');
});

it('has no destroy route', function () {
    [, $token] = userWithRole('owner');
    $template = MessageTemplate::factory()->create();

    $this->withToken($token)->deleteJson("/api/v1/message-templates/{$template->ulid}")
        ->assertStatus(405);
});

it('forbids a technician from managing templates', function () {
    [, $token] = userWithRole('technician');

    $this->withToken($token)->getJson('/api/v1/message-templates')->assertStatus(403);
});
