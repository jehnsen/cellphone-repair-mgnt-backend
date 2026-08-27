<?php

use App\Models\Acquisition;
use App\Models\Branch;
use App\Models\Product;
use App\Models\SerializedUnit;

it('opens a refurb job for a completed acquisition, moving the unit to for_repair', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $unit = SerializedUnit::factory()->create(['branch_id' => $branch->id]);
    $acquisition = Acquisition::factory()->create(['branch_id' => $branch->id, 'resulting_serialized_unit_id' => $unit->id]);

    $this->withToken($token)->postJson('/api/v1/refurb-jobs', [
        'acquisition_ulid' => $acquisition->ulid,
        'labor_cost' => 500,
    ])->assertStatus(201)
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.serialized_unit.status', 'for_repair');
});

it('rejects opening a refurb job for an acquisition not yet completed', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $acquisition = Acquisition::factory()->create(['branch_id' => $branch->id]);

    $this->withToken($token)->postJson('/api/v1/refurb-jobs', [
        'acquisition_ulid' => $acquisition->ulid,
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('adds a part line, consuming stock and recomputing landed_cost, then completes the job', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $unit = SerializedUnit::factory()->create(['branch_id' => $branch->id, 'acquisition_cost' => 3000]);
    $acquisition = Acquisition::factory()->create([
        'branch_id' => $branch->id,
        'resulting_serialized_unit_id' => $unit->id,
        'offered_price' => 3000,
    ]);
    $part = Product::factory()->part()->create();

    $created = $this->withToken($token)->postJson('/api/v1/refurb-jobs', [
        'acquisition_ulid' => $acquisition->ulid,
        'labor_cost' => 500,
    ])->assertStatus(201);
    $jobUlid = $created->json('data.ulid');

    $this->withToken($token)->postJson("/api/v1/refurb-jobs/{$jobUlid}/lines", [
        'product_ulid' => $part->ulid,
        'quantity' => 1,
        'unit_cost' => 800,
    ])->assertStatus(201);

    $show = $this->withToken($token)->getJson("/api/v1/refurb-jobs/{$jobUlid}")->assertOk();
    // landed_cost = parts (800) + labor (500) + acquisition offered_price (3000)
    expect($show->json('data.landed_cost'))->toBe('4300.00');

    $this->withToken($token)->postJson("/api/v1/refurb-jobs/{$jobUlid}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.serialized_unit.status', 'in_stock');

    expect($unit->fresh()->acquisition_cost)->toBe('4300.00');
});
