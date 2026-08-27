<?php

use App\Models\Branch;
use App\Models\Sale;
use App\Models\Shift;

it('creates an installment plan, splitting the financed amount evenly across the term', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('manager', $branch);
    $sale = Sale::factory()->create(['branch_id' => $branch->id, 'total' => 6000]);

    $response = $this->withToken($token)->postJson('/api/v1/installment-plans', [
        'sale_ulid' => $sale->ulid,
        'downpayment' => 1200,
        'term_months' => 3,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.term_months', 3)
        ->assertJsonCount(3, 'data.schedules');

    $schedules = collect($response->json('data.schedules'));
    expect($schedules->sum(fn ($s) => (float) $s['amount_due']))->toBe(4800.0);
});

it('pays a schedule, recording a payment against the sale and marking it paid', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = userWithRole('manager', $branch);
    Shift::factory()->open()->create(['branch_id' => $branch->id, 'cashier_id' => $user->id]);
    $sale = Sale::factory()->create(['branch_id' => $branch->id, 'total' => 3000]);

    $created = $this->withToken($token)->postJson('/api/v1/installment-plans', [
        'sale_ulid' => $sale->ulid,
        'downpayment' => 0,
        'term_months' => 3,
    ])->assertStatus(201);

    $planUlid = $created->json('data.ulid');
    $scheduleUlid = $created->json('data.schedules.0.ulid');
    $amountDue = $created->json('data.schedules.0.amount_due');

    $this->withToken($token)->postJson("/api/v1/installment-plans/{$planUlid}/schedules/{$scheduleUlid}/pay", [
        'method' => 'cash',
        'amount' => $amountDue,
    ])->assertOk()->assertJsonPath('data.status', 'paid');
});

it('rejects paying an already fully-paid schedule', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = userWithRole('manager', $branch);
    Shift::factory()->open()->create(['branch_id' => $branch->id, 'cashier_id' => $user->id]);
    $sale = Sale::factory()->create(['branch_id' => $branch->id, 'total' => 1000]);

    $created = $this->withToken($token)->postJson('/api/v1/installment-plans', [
        'sale_ulid' => $sale->ulid,
        'downpayment' => 0,
        'term_months' => 1,
    ])->assertStatus(201);

    $planUlid = $created->json('data.ulid');
    $scheduleUlid = $created->json('data.schedules.0.ulid');
    $amountDue = $created->json('data.schedules.0.amount_due');

    $this->withToken($token)->postJson("/api/v1/installment-plans/{$planUlid}/schedules/{$scheduleUlid}/pay", [
        'method' => 'cash',
        'amount' => $amountDue,
    ])->assertOk();

    $this->withToken($token)->postJson("/api/v1/installment-plans/{$planUlid}/schedules/{$scheduleUlid}/pay", [
        'method' => 'cash',
        'amount' => 1,
    ])->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATUS_TRANSITION');
});
