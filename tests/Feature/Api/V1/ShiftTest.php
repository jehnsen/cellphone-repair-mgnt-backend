<?php

use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\Payment;
use App\Models\Shift;

it('opens a shift for the authenticated cashier', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('cashier', $branch);

    $this->withToken($token)->postJson('/api/v1/shifts/open', [
        'opening_float' => 2000,
    ])->assertStatus(201)
        ->assertJsonPath('data.opening_float', '2000.00')
        ->assertJsonPath('data.is_open', true);
});

it('rejects opening a second shift while one is already open', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = userWithRole('cashier', $branch);
    Shift::factory()->open()->create(['branch_id' => $branch->id, 'cashier_id' => $user->id]);

    $this->withToken($token)->postJson('/api/v1/shifts/open', [
        'opening_float' => 2000,
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('closes a shift, reconciling cash payments and cash movements into expected_cash', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = userWithRole('cashier', $branch);
    $shift = Shift::factory()->open()->create(['branch_id' => $branch->id, 'cashier_id' => $user->id, 'opening_float' => 1000]);

    Payment::factory()->create(['payable_type' => 'sale', 'method' => 'cash', 'amount' => 500, 'shift_id' => $shift->id]);
    Payment::factory()->gcash()->create(['payable_type' => 'sale', 'amount' => 999, 'shift_id' => $shift->id]);
    CashMovement::factory()->create(['shift_id' => $shift->id, 'direction' => 'out', 'amount' => 200, 'actor_id' => $user->id]);

    // expected_cash = 1000 (opening) + 500 (cash payment) - 200 (cash out) = 1300; gcash never counts.
    $this->withToken($token)->postJson("/api/v1/shifts/{$shift->ulid}/close", [
        'counted_cash' => 1300,
    ])->assertOk()
        ->assertJsonPath('data.expected_cash', '1300.00')
        ->assertJsonPath('data.variance', '0.00')
        ->assertJsonPath('data.is_open', false);
});

it('rejects closing an already-closed shift', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = userWithRole('cashier', $branch);
    $shift = Shift::factory()->create(['branch_id' => $branch->id, 'cashier_id' => $user->id]);

    $this->withToken($token)->postJson("/api/v1/shifts/{$shift->ulid}/close", [
        'counted_cash' => 100,
    ])->assertStatus(409)->assertJsonPath('error.code', 'SHIFT_NOT_OPEN');
});

it("forbids a cashier from closing another cashier's shift", function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('cashier', $branch);
    $otherShift = Shift::factory()->open()->create(['branch_id' => $branch->id]);

    $this->withToken($token)->postJson("/api/v1/shifts/{$otherShift->ulid}/close", [
        'counted_cash' => 100,
    ])->assertStatus(403);
});

it('records a cash movement against an open shift', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = userWithRole('cashier', $branch);
    $shift = Shift::factory()->open()->create(['branch_id' => $branch->id, 'cashier_id' => $user->id]);

    $this->withToken($token)->postJson("/api/v1/shifts/{$shift->ulid}/cash-movements", [
        'direction' => 'out',
        'amount' => 300,
        'reason' => 'Bank deposit',
    ])->assertStatus(201)->assertJsonPath('data.amount', '300.00');
});

it('rejects a cash movement against a closed shift', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = userWithRole('cashier', $branch);
    $shift = Shift::factory()->create(['branch_id' => $branch->id, 'cashier_id' => $user->id]);

    $this->withToken($token)->postJson("/api/v1/shifts/{$shift->ulid}/cash-movements", [
        'direction' => 'in',
        'amount' => 100,
        'reason' => 'Change fund',
    ])->assertStatus(409)->assertJsonPath('error.code', 'SHIFT_NOT_OPEN');
});
