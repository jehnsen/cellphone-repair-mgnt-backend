<?php

use App\Models\Branch;
use App\Models\RepairTicket;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('uploads a ticket photo and returns a signed url instead of binary', function () {
    Storage::fake('local');

    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id]);
    $file = UploadedFile::fake()->image('intake.jpg');

    $response = $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/photos", [
        'phase' => 'intake',
        'photo' => $file,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.phase', 'intake')
        ->assertJsonStructure(['data' => ['ulid', 'sha256_hash', 'url', 'captured_at']]);

    expect($response->json('data.url'))->toBeString()->not->toBeEmpty();
});

it('lists photos for a ticket each with their own signed url', function () {
    Storage::fake('local');

    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id]);

    $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/photos", [
        'phase' => 'pre_repair',
        'photo' => UploadedFile::fake()->image('before.jpg'),
    ])->assertStatus(201);

    $response = $this->withToken($token)->getJson("/api/v1/tickets/{$ticket->ulid}/photos")->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.url'))->toBeString()->not->toBeEmpty();
});

it('rejects an unsupported photo phase', function () {
    Storage::fake('local');

    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id]);

    $this->withToken($token)->postJson("/api/v1/tickets/{$ticket->ulid}/photos", [
        'phase' => 'not_a_real_phase',
        'photo' => UploadedFile::fake()->image('x.jpg'),
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
});
