<?php

use App\Models\DevicePart;
use App\Models\IssuePartLink;
use App\Models\RepairFinding;
use App\Models\RepairTicket;
use App\Models\TicketDiagnosisSnapshot;
use App\Models\VerificationToken;
use App\Support\Diagnosis\IssueSource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The rig is reference data, seeded by DevicePartSeeder as part of the base
 * install. Tests that need it seed it rather than hand-rolling parts, so they
 * exercise the mapping the shop actually ships with.
 */
function seedRig(): void
{
    test()->seed(Database\Seeders\DevicePartSeeder::class);
}

it('returns the phone rig with geometry, in assembly order', function () {
    seedRig();
    [, $token] = userWithRole('technician');

    $response = $this->withToken($token)->getJson('/api/v1/device-parts');

    $response->assertOk()
        ->assertJsonPath('data.0.key', 'front_glass')
        ->assertJsonPath('data.0.category', 'display');

    $parts = $response->json('data');

    expect($parts)->toHaveCount(15);
    // Geometry is what the client builds the scene from; a part without it
    // would render at the origin on top of everything else.
    expect($parts[0])->toHaveKeys(['position', 'size', 'explode', 'blurb']);
    expect(array_column($parts, 'sort_order'))
        ->toBe(array_values(collect($parts)->pluck('sort_order')->sort()->all()));
});

/* Two actors, two tests: the auth guard caches the resolved user for the rest
   of the test process, so a second withToken() in the same it() does not
   reliably re-authenticate (see CLAUDE.md, "Sanctum gotcha"). */

it('hides a deactivated part from the visualizer', function () {
    seedRig();
    DevicePart::where('key', 'sim_tray')->update(['is_active' => false]);

    [, $token] = userWithRole('technician');

    expect($this->withToken($token)->getJson('/api/v1/device-parts')->json('data'))
        ->toHaveCount(14);
});

it('still shows a deactivated part to someone who can manage the catalog', function () {
    seedRig();
    DevicePart::where('key', 'sim_tray')->update(['is_active' => false]);

    // A part is deactivated rather than deleted precisely so a stored
    // snapshot naming it keeps resolving — so management has to be able to
    // see it, and turn it back on.
    [, $token] = userWithRole('owner');

    expect(
        $this->withToken($token)
            ->getJson('/api/v1/device-parts?include_inactive=1')
            ->json('data')
    )->toHaveCount(15);
});

it('returns both issue vocabularies with the parts each implicates', function () {
    seedRig();
    [, $token] = userWithRole('technician');

    $response = $this->withToken($token)->getJson('/api/v1/issue-types');

    $response->assertOk();

    $tags = collect($response->json('data.problem_tag'))->keyBy('key');
    $defects = collect($response->json('data.defect'))->keyBy('key');

    // Ranked, not merely present: the first key is the part the technician
    // means, and the camera frames it.
    expect($tags['screen']['part_keys'])->toBe(['front_glass', 'display_assembly']);
    // The bench vocabulary is the sharper one — by this point somebody has
    // actually looked, so the panel leads rather than the glass.
    expect($defects['screen']['part_keys'])->toBe(['display_assembly', 'front_glass']);

    // An honest three-part answer stays three parts.
    expect($tags['no_power']['part_keys'])->toBe(['battery', 'logic_board', 'power_button']);

    // 'other' maps to nothing on purpose: highlighting an arbitrary part
    // would be worse than highlighting none.
    expect($defects['other']['part_keys'])->toBe([]);

    // Labels are spelled out, not derived — Str::headline() would give
    // "Power Ic", which is how nobody in a repair shop says it.
    expect($defects['power_ic']['label'])->toBe('Power IC');
    expect($defects['camera_rear']['label'])->toBe('Rear camera');
});

it('lets an owner remap an issue to different parts, wholesale', function () {
    seedRig();
    [, $token] = userWithRole('owner');

    $battery = DevicePart::where('key', 'battery')->firstOrFail();

    $this->withToken($token)->putJson('/api/v1/issue-types/parts', [
        'issue_source' => 'defect',
        'issue_key' => 'buttons',
        'parts' => [['part_ulid' => $battery->ulid, 'rank' => 1]],
    ])->assertOk();

    $links = IssuePartLink::query()
        ->forIssue(IssueSource::Defect, 'buttons')
        ->pluck('device_part_id');

    // Replaced, not appended — the previous power_button/volume_flex pair is
    // gone rather than sitting alongside the new mapping.
    expect($links->all())->toBe([$battery->id]);
});

it('rejects an issue key that does not belong to the named vocabulary', function () {
    seedRig();
    [, $token] = userWithRole('owner');
    $battery = DevicePart::where('key', 'battery')->firstOrFail();

    // 'water_damage' is a problem tag, not a defect. The two vocabularies
    // overlap, so neither list alone would catch this.
    $this->withToken($token)->putJson('/api/v1/issue-types/parts', [
        'issue_source' => 'defect',
        'issue_key' => 'water_damage',
        'parts' => [['part_ulid' => $battery->ulid]],
    ])->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonPath('error.details.0.field', 'issue_key');
});

it('does not let a technician edit the rig or the mapping', function () {
    seedRig();
    // A manager *can*: they already maintain brands, models and services, and
    // the rig is catalog data on the same footing. A technician holds
    // catalog.view alone.
    [, $token] = userWithRole('technician');
    $battery = DevicePart::where('key', 'battery')->firstOrFail();

    $this->withToken($token)->putJson('/api/v1/issue-types/parts', [
        'issue_source' => 'defect',
        'issue_key' => 'buttons',
        'parts' => [['part_ulid' => $battery->ulid]],
    ])->assertForbidden();

    $this->withToken($token)->postJson('/api/v1/device-parts', [
        'key' => 'nfc_coil',
        'label' => 'NFC coil',
        'category' => 'connectivity',
        'position' => ['x' => 0, 'y' => 0, 'z' => -2],
        'size' => ['x' => 20, 'y' => 20, 'z' => 1],
    ])->assertForbidden();
});

it('stores a diagnosis snapshot against the ticket and reads it back', function () {
    Storage::fake('local');
    seedRig();

    [$manager, $token] = userWithRole('manager');
    $ticket = RepairTicket::factory()->create([
        'branch_id' => $manager->branch_id,
        'status' => 'in_repair',
    ]);

    $this->withToken($token)->post(
        "/api/v1/tickets/{$ticket->ulid}/diagnosis-snapshots",
        [
            'image' => UploadedFile::fake()->image('diagnosis.png', 800, 800),
            'issue_source' => 'defect',
            // Multipart carries these as JSON strings; the request decodes
            // them before the array rules run.
            'issue_keys' => json_encode(['charging_port', 'microphone']),
            'part_keys' => json_encode(['charging_port_flex']),
            'camera' => json_encode(['x' => 1.0, 'y' => 2.0, 'z' => 3.0]),
            'note' => 'Shown to the customer before approving the quote.',
        ],
        ['Accept' => 'application/json'],
    )->assertStatus(201)
        ->assertJsonPath('data.issue_source', 'defect')
        ->assertJsonPath('data.part_keys', ['charging_port_flex']);

    $snapshot = TicketDiagnosisSnapshot::where('repair_ticket_id', $ticket->id)->firstOrFail();

    expect($snapshot->issue_keys)->toBe(['charging_port', 'microphone']);
    expect($snapshot->captured_by)->toBe($manager->id);
    Storage::disk('local')->assertExists($snapshot->storage_path);

    $this->withToken($token)
        ->getJson("/api/v1/tickets/{$ticket->ulid}/diagnosis-snapshots")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.ulid', $snapshot->ulid);
});

it('refuses a snapshot naming a part the rig does not have', function () {
    Storage::fake('local');
    seedRig();

    [$manager, $token] = userWithRole('manager');
    $ticket = RepairTicket::factory()->create(['branch_id' => $manager->branch_id]);

    $this->withToken($token)->post(
        "/api/v1/tickets/{$ticket->ulid}/diagnosis-snapshots",
        [
            'image' => UploadedFile::fake()->image('diagnosis.png'),
            'issue_source' => 'defect',
            'issue_keys' => json_encode(['battery']),
            'part_keys' => json_encode(['flux_capacitor']),
        ],
        ['Accept' => 'application/json'],
    )->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonPath('error.details.0.field', 'part_keys.0');
});

it('serves the customer-facing diagnosis from the ticket finding, redacted', function () {
    seedRig();

    [$manager] = userWithRole('manager');
    $ticket = RepairTicket::factory()->create([
        'branch_id' => $manager->branch_id,
        'status' => 'in_repair',
        'problem_tags' => ['screen'],
    ]);
    RepairFinding::factory()->create([
        'repair_ticket_id' => $ticket->id,
        'summary' => 'Charging port pins corroded.',
        'root_cause' => 'liquid_ingress',
        'defects' => ['charging_port'],
        'resolution' => 'part_replaced',
        'technician_notes' => 'Internal only — never leaves the shop.',
    ]);
    $verification = VerificationToken::factory()->create(['repair_ticket_id' => $ticket->id]);

    // Unauthenticated on purpose: this is the link a customer opens.
    $response = $this->getJson("/api/v1/public/verify/{$verification->token}/diagnosis");

    $response->assertOk()
        // The finding wins over the intake tags once one exists.
        ->assertJsonPath('data.issues.0.key', 'charging_port')
        ->assertJsonPath('data.implicated_part_keys', ['charging_port_flex'])
        ->assertJsonPath('data.finding.summary', 'Charging port pins corroded.');

    // The rig travels with it, so the customer's browser can draw the scene
    // without a second, authenticated call.
    expect($response->json('data.parts'))->toHaveCount(15);

    // Redaction, on the same terms as the chain-of-custody sibling.
    $body = $response->getContent();
    expect($body)->not->toContain('Internal only');
    expect($body)->not->toContain($ticket->claim_code);
    expect($response->json('data'))->not->toHaveKey('customer');
});

it('falls back to the intake problem tags when nothing is on the bench yet', function () {
    seedRig();

    [$manager] = userWithRole('manager');
    $ticket = RepairTicket::factory()->create([
        'branch_id' => $manager->branch_id,
        'problem_tags' => ['no_power'],
    ]);
    $verification = VerificationToken::factory()->create(['repair_ticket_id' => $ticket->id]);

    $this->getJson("/api/v1/public/verify/{$verification->token}/diagnosis")
        ->assertOk()
        ->assertJsonPath('data.issues.0.source', 'problem_tag')
        ->assertJsonPath('data.issues.0.label', 'No power')
        ->assertJsonPath('data.implicated_part_keys', ['battery', 'logic_board', 'power_button'])
        ->assertJsonPath('data.finding', null);
});

it('does not serve a diagnosis for a revoked link', function () {
    seedRig();

    [$manager] = userWithRole('manager');
    $ticket = RepairTicket::factory()->create(['branch_id' => $manager->branch_id]);
    $verification = VerificationToken::factory()->create([
        'repair_ticket_id' => $ticket->id,
        'revoked_at' => now(),
    ]);

    $this->getJson("/api/v1/public/verify/{$verification->token}/diagnosis")
        ->assertNotFound();
});
