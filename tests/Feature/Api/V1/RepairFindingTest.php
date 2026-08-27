<?php

use App\Models\RepairFinding;
use App\Models\RepairTicket;
use App\Models\TicketEvent;
use Illuminate\Database\QueryException;

function findingPayload(array $overrides = []): array
{
    return array_merge([
        'summary' => 'Charging port pins corroded from liquid ingress.',
        'details' => 'Board otherwise clean.',
        'root_cause' => 'liquid_ingress',
        'defects' => ['charging_port', 'sim_reader'],
        'resolution' => 'part_replaced',
        'technician_notes' => 'Advise against non-original chargers.',
        'qc_passed' => true,
    ], $overrides);
}

it('creates the findings record on first PUT and returns 201', function () {
    [$manager, $token] = userWithRole('manager');
    $ticket = RepairTicket::factory()->create(['branch_id' => $manager->branch_id, 'status' => 'in_repair']);

    $response = $this->withToken($token)->putJson("/api/v1/tickets/{$ticket->ulid}/finding", findingPayload());

    $response->assertStatus(201)
        ->assertJsonPath('data.root_cause', 'liquid_ingress')
        ->assertJsonPath('data.defects', ['charging_port', 'sim_reader'])
        ->assertJsonPath('data.resolution', 'part_replaced')
        ->assertJsonPath('data.qc_passed', true);

    expect(RepairFinding::where('repair_ticket_id', $ticket->id)->count())->toBe(1);
});

it('updates the same record in place on a subsequent PUT and returns 200', function () {
    [$manager, $token] = userWithRole('manager');
    $ticket = RepairTicket::factory()->create(['branch_id' => $manager->branch_id, 'status' => 'in_repair']);
    RepairFinding::factory()->create(['repair_ticket_id' => $ticket->id, 'summary' => 'Initial guess.']);

    $this->withToken($token)->putJson("/api/v1/tickets/{$ticket->ulid}/finding", findingPayload([
        'summary' => 'Revised: charging port.',
    ]))->assertStatus(200)->assertJsonPath('data.summary', 'Revised: charging port.');

    expect(RepairFinding::where('repair_ticket_id', $ticket->id)->count())->toBe(1);
});

it('enforces one findings record per ticket', function () {
    [$manager] = userWithRole('manager');
    $ticket = RepairTicket::factory()->create(['branch_id' => $manager->branch_id]);
    RepairFinding::factory()->create(['repair_ticket_id' => $ticket->id]);

    expect(fn () => RepairFinding::factory()->create(['repair_ticket_id' => $ticket->id]))
        ->toThrow(QueryException::class);
});

it('stamps qc_checked_at and qc_checked_by when qc_passed first gets a verdict', function () {
    [$manager, $token] = userWithRole('manager');
    $ticket = RepairTicket::factory()->create(['branch_id' => $manager->branch_id, 'status' => 'qc']);

    $this->withToken($token)->putJson("/api/v1/tickets/{$ticket->ulid}/finding", findingPayload([
        'qc_passed' => true,
    ]))->assertStatus(201)
        ->assertJsonPath('data.qc_passed', true)
        ->assertJsonPath('data.qc_checked_by.ulid', $manager->ulid);

    $finding = RepairFinding::where('repair_ticket_id', $ticket->id)->sole();
    expect($finding->qc_checked_at)->not->toBeNull();
    expect($finding->qc_checked_by_id)->toBe($manager->id);
});

it('does not restamp qc_checked_by on a later edit that keeps the verdict', function () {
    [$manager, $token] = userWithRole('manager');
    [$other] = userWithRole('technician', $manager->branch);
    $ticket = RepairTicket::factory()->create(['branch_id' => $manager->branch_id, 'status' => 'qc']);
    RepairFinding::factory()->create([
        'repair_ticket_id' => $ticket->id,
        'qc_passed' => true,
        'qc_checked_at' => now()->subDay(),
        'qc_checked_by_id' => $other->id,
    ]);

    $this->withToken($token)->putJson("/api/v1/tickets/{$ticket->ulid}/finding", findingPayload())
        ->assertStatus(200)
        ->assertJsonPath('data.qc_checked_by.ulid', $other->ulid);
});

it('requires details when the root cause is other', function () {
    [$manager, $token] = userWithRole('manager');
    $ticket = RepairTicket::factory()->create(['branch_id' => $manager->branch_id, 'status' => 'in_repair']);

    $this->withToken($token)->putJson("/api/v1/tickets/{$ticket->ulid}/finding", findingPayload([
        'root_cause' => 'other',
        'details' => null,
    ]))->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonPath('error.details.0.field', 'details');
});

it('requires details when the resolution is unrepairable', function () {
    [$manager, $token] = userWithRole('manager');
    $ticket = RepairTicket::factory()->create(['branch_id' => $manager->branch_id, 'status' => 'in_repair']);

    $this->withToken($token)->putJson("/api/v1/tickets/{$ticket->ulid}/finding", findingPayload([
        'resolution' => 'unrepairable',
        'details' => null,
    ]))->assertStatus(422)->assertJsonPath('error.details.0.field', 'details');
});

it('rejects an unknown root cause', function () {
    [$manager, $token] = userWithRole('manager');
    $ticket = RepairTicket::factory()->create(['branch_id' => $manager->branch_id, 'status' => 'in_repair']);

    $this->withToken($token)->putJson("/api/v1/tickets/{$ticket->ulid}/finding", findingPayload([
        'root_cause' => 'gremlins',
    ]))->assertStatus(422)->assertJsonPath('error.details.0.field', 'root_cause');
});

it('rejects duplicate defect values', function () {
    [$manager, $token] = userWithRole('manager');
    $ticket = RepairTicket::factory()->create(['branch_id' => $manager->branch_id, 'status' => 'in_repair']);

    $this->withToken($token)->putJson("/api/v1/tickets/{$ticket->ulid}/finding", findingPayload([
        'defects' => ['screen', 'screen'],
    ]))->assertStatus(422);
});

it('409s when recording a finding against a released ticket', function () {
    [$manager, $token] = userWithRole('manager');
    $ticket = RepairTicket::factory()->create(['branch_id' => $manager->branch_id, 'status' => 'released']);

    $this->withToken($token)->putJson("/api/v1/tickets/{$ticket->ulid}/finding", findingPayload())
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'INVALID_STATUS_TRANSITION');
});

it('appends a finding_recorded timeline event on create and on edit', function () {
    [$manager, $token] = userWithRole('manager');
    $ticket = RepairTicket::factory()->create(['branch_id' => $manager->branch_id, 'status' => 'in_repair']);

    $this->withToken($token)->putJson("/api/v1/tickets/{$ticket->ulid}/finding", findingPayload());
    $this->withToken($token)->putJson("/api/v1/tickets/{$ticket->ulid}/finding", findingPayload([
        'summary' => 'Revised.',
    ]));

    $events = TicketEvent::where('repair_ticket_id', $ticket->id)
        ->where('event_type', 'finding_recorded')
        ->orderBy('id')
        ->get();

    expect($events)->toHaveCount(2);
    expect($events[0]->note)->toContain('Findings recorded: charging port, sim reader — liquid ingress (part replaced).');
    expect($events[1]->note)->toContain('Findings updated:');
});

it('does not change ticket status when a finding is recorded', function () {
    [$manager, $token] = userWithRole('manager');
    $ticket = RepairTicket::factory()->create(['branch_id' => $manager->branch_id, 'status' => 'in_repair']);

    $this->withToken($token)->putJson("/api/v1/tickets/{$ticket->ulid}/finding", findingPayload());

    expect($ticket->fresh()->status)->toBe('in_repair');
});

it('returns the findings record via GET', function () {
    [$manager, $token] = userWithRole('manager');
    $ticket = RepairTicket::factory()->create(['branch_id' => $manager->branch_id]);
    RepairFinding::factory()->create([
        'repair_ticket_id' => $ticket->id,
        'recorded_by_id' => $manager->id,
        'summary' => 'Corroded port.',
    ]);

    $this->withToken($token)->getJson("/api/v1/tickets/{$ticket->ulid}/finding")
        ->assertOk()
        ->assertJsonPath('data.summary', 'Corroded port.')
        ->assertJsonPath('data.recorded_by.ulid', $manager->ulid);
});

it('404s on GET when no finding has been recorded', function () {
    [$manager, $token] = userWithRole('manager');
    $ticket = RepairTicket::factory()->create(['branch_id' => $manager->branch_id]);

    $this->withToken($token)->getJson("/api/v1/tickets/{$ticket->ulid}/finding")
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'NOT_FOUND');
});

it('lets a cashier read a finding but not write one', function () {
    [$manager] = userWithRole('manager');
    $ticket = RepairTicket::factory()->create(['branch_id' => $manager->branch_id]);
    RepairFinding::factory()->create(['repair_ticket_id' => $ticket->id]);

    [, $cashierToken] = userWithRole('cashier', $manager->branch);

    $this->withToken($cashierToken)->getJson("/api/v1/tickets/{$ticket->ulid}/finding")->assertOk();
});

it('forbids a cashier from writing a finding', function () {
    [$manager] = userWithRole('manager');
    $ticket = RepairTicket::factory()->create(['branch_id' => $manager->branch_id, 'status' => 'in_repair']);

    [, $cashierToken] = userWithRole('cashier', $manager->branch);

    $this->withToken($cashierToken)->putJson("/api/v1/tickets/{$ticket->ulid}/finding", findingPayload())
        ->assertStatus(403);
});

it('includes a compact finding summary on ticket show but not on index', function () {
    [$manager, $token] = userWithRole('manager');
    $ticket = RepairTicket::factory()->create(['branch_id' => $manager->branch_id]);
    RepairFinding::factory()->create([
        'repair_ticket_id' => $ticket->id,
        'summary' => 'Corroded port.',
        'root_cause' => 'liquid_ingress',
        'resolution' => 'part_replaced',
        'qc_passed' => true,
    ]);

    $this->withToken($token)->getJson("/api/v1/tickets/{$ticket->ulid}")
        ->assertOk()
        ->assertJsonPath('data.finding.summary', 'Corroded port.')
        ->assertJsonPath('data.finding.root_cause', 'liquid_ingress');

    $index = $this->withToken($token)->getJson('/api/v1/tickets')->assertOk();
    expect($index->json('data.0'))->not->toHaveKey('finding');
});

it('exposes the finding vocabularies via GET /meta/enums', function () {
    [, $token] = userWithRole('cashier');

    $this->withToken($token)->getJson('/api/v1/meta/enums')
        ->assertOk()
        ->assertJsonPath('data.root_cause.0', 'drop_impact')
        ->assertJsonStructure(['data' => ['root_cause', 'defects', 'resolution']]);
});
