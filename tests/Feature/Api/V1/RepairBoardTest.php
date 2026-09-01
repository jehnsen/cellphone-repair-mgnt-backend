<?php

use App\Models\Branch;
use App\Models\RepairTicket;
use App\Services\RepairBoardService;

it('groups open tickets into board columns', function () {
    $branch = Branch::factory()->create();

    RepairTicket::factory()->count(2)->create(['branch_id' => $branch->id, 'status' => 'received']);
    RepairTicket::factory()->count(3)->create(['branch_id' => $branch->id, 'status' => 'in_repair']);

    [, $token] = userWithRole('cashier', $branch);

    $response = $this->withToken($token)->getJson('/api/v1/tickets/board')
        ->assertOk()
        ->assertJsonPath('data.scope', 'branch');

    $columns = collect($response->json('data.columns'))->keyBy('status');

    expect($columns->get('received')['count'])->toBe(2)
        ->and($columns->get('in_repair')['count'])->toBe(3)
        ->and($columns->keys()->all())->toBe(RepairBoardService::COLUMNS);
});

it('keeps terminal tickets off the board', function () {
    $branch = Branch::factory()->create();

    RepairTicket::factory()->count(2)->create(['branch_id' => $branch->id, 'status' => 'received']);
    RepairTicket::factory()->count(4)->create(['branch_id' => $branch->id, 'status' => 'released']);
    RepairTicket::factory()->count(2)->create(['branch_id' => $branch->id, 'status' => 'unrepairable']);

    [, $token] = userWithRole('cashier', $branch);

    $response = $this->withToken($token)->getJson('/api/v1/tickets/board')->assertOk();

    $total = collect($response->json('data.columns'))->sum('count');

    expect($total)->toBe(2);
});

it('never puts pricing or unlock secrets on a board card', function () {
    $branch = Branch::factory()->create();

    RepairTicket::factory()->create([
        'branch_id' => $branch->id,
        'status' => 'received',
        'unlock_method' => 'pin',
        'unlock_value' => '1234',
        'estimated_cost' => 1500,
    ]);

    [, $token] = userWithRole('cashier', $branch);

    $response = $this->withToken($token)->getJson('/api/v1/tickets/board')->assertOk();

    $card = $response->json('data.columns.0.tickets.0');

    // The board often faces the shop floor — it carries no money and no
    // secrets, only what belongs on a job card.
    foreach (['unlock_value', 'unlock_method', 'estimated_cost', 'approved_amount', 'balance', 'claim_code'] as $forbidden) {
        expect($card)->not->toHaveKey($forbidden);
    }

    expect($card)->toHaveKeys(['ulid', 'ticket_number', 'status', 'customer_name', 'device', 'is_overdue']);
});

it('scopes the board to the caller branch', function () {
    $home = Branch::factory()->create(['code' => 'HOME']);
    $other = Branch::factory()->create(['code' => 'OTHR']);

    RepairTicket::factory()->count(2)->create(['branch_id' => $home->id, 'status' => 'received']);
    RepairTicket::factory()->count(5)->create(['branch_id' => $other->id, 'status' => 'received']);

    [, $token] = userWithRole('cashier', $home);

    $response = $this->withToken($token)->getJson('/api/v1/tickets/board')->assertOk();

    expect(collect($response->json('data.columns'))->sum('count'))->toBe(2);
});

it('spans both branches on the board for an owner asking for all', function () {
    $home = Branch::factory()->create(['code' => 'HOME']);
    $other = Branch::factory()->create(['code' => 'OTHR']);

    RepairTicket::factory()->count(2)->create(['branch_id' => $home->id, 'status' => 'received']);
    RepairTicket::factory()->count(5)->create(['branch_id' => $other->id, 'status' => 'received']);

    [, $token] = userWithRole('owner', $home);

    $response = $this->withToken($token)->getJson('/api/v1/tickets/board?branch=all')
        ->assertOk()
        ->assertJsonPath('data.scope', 'all_branches');

    expect(collect($response->json('data.columns'))->sum('count'))->toBe(7);
});

it('flags an overdue ticket', function () {
    $branch = Branch::factory()->create();

    RepairTicket::factory()->create([
        'branch_id' => $branch->id,
        'status' => 'in_repair',
        'promised_date' => now()->subDays(3),
    ]);

    [, $token] = userWithRole('cashier', $branch);

    $response = $this->withToken($token)->getJson('/api/v1/tickets/board')->assertOk();

    $card = collect($response->json('data.columns'))
        ->firstWhere('status', 'in_repair')['tickets'][0];

    expect($card['is_overdue'])->toBeTrue();
});
