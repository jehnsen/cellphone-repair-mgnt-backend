<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\RepairTicket;
use App\Models\Sale;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\SystemResetService;
use App\Support\Api\ApiException;

/**
 * Unlike SystemResetTest's fresh-install coverage, this hits the service
 * for real: clearTransactionalData() is plain `DELETE FROM` inside a
 * transaction (no `migrate:fresh`/TRUNCATE, both DDL that would implicitly
 * commit and break RefreshDatabase), so it's safe to run against seeded
 * data and assert on what survives.
 */
it('lets an owner clear transactional data while preserving setup data', function () {
    [$owner, $token] = userWithRole('owner');

    $branch = Branch::first();
    $product = Product::factory()->create();
    // Tied to the owner's own branch — BranchContext (the singleton
    // BranchScope reads from) is still scoped to $owner from the
    // withToken() call below by the time the post-clear assertions run,
    // so a customer in some other branch would read as "not found" even
    // though it was never touched (see CLAUDE.md's BranchContext gotcha).
    $customer = Customer::factory()->for($branch)->create();

    RepairTicket::factory()->for($branch)->for($customer)->create();
    Sale::factory()->for($branch)->create();
    StockMovement::factory()->for($product)->for($branch)->create();
    StockLevel::factory()->for($product)->for($branch)->create();

    $this->withToken($token)
        ->postJson('/api/v1/system/clear-transactional-data', ['confirm' => 'CLEAR_SAMPLE_DATA'])
        ->assertOk()
        ->assertJsonPath('data.status', 'transactional_data_cleared')
        ->assertJsonPath('data.cleared.repair_tickets', 1)
        ->assertJsonPath('data.cleared.sales', 1)
        ->assertJsonPath('data.cleared.stock_movements', 1)
        ->assertJsonPath('data.cleared.stock_levels', 1);

    expect(RepairTicket::count())->toBe(0)
        ->and(Sale::count())->toBe(0)
        ->and(StockMovement::count())->toBe(0)
        ->and(StockLevel::count())->toBe(0);

    // Setup data — branches, users, catalog, customers — is untouched.
    expect(Branch::count())->toBeGreaterThan(0)
        ->and(User::count())->toBeGreaterThan(0)
        ->and(Product::query()->whereKey($product->id)->exists())->toBeTrue()
        ->and(Customer::query()->whereKey($customer->id)->exists())->toBeTrue()
        ->and(User::query()->whereKey($owner->id)->exists())->toBeTrue();
});

it('rejects clearing transactional data without the confirmation phrase', function () {
    [, $token] = userWithRole('owner');

    $this->withToken($token)
        ->postJson('/api/v1/system/clear-transactional-data', [])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('rejects clearing transactional data with the fresh-install confirmation phrase', function () {
    [, $token] = userWithRole('owner');

    $this->withToken($token)
        ->postJson('/api/v1/system/clear-transactional-data', ['confirm' => 'RESET'])
        ->assertStatus(422);
});

it('denies clearing transactional data to a manager', function () {
    [, $token] = userWithRole('manager');

    $this->withToken($token)
        ->postJson('/api/v1/system/clear-transactional-data', ['confirm' => 'CLEAR_SAMPLE_DATA'])
        ->assertStatus(403);
});

it('denies clearing transactional data to a cashier', function () {
    [, $token] = userWithRole('cashier');

    $this->withToken($token)
        ->postJson('/api/v1/system/clear-transactional-data', ['confirm' => 'CLEAR_SAMPLE_DATA'])
        ->assertStatus(403);
});

it('requires authentication to clear transactional data', function () {
    $this->postJson('/api/v1/system/clear-transactional-data', ['confirm' => 'CLEAR_SAMPLE_DATA'])
        ->assertStatus(401);
});

it('refuses to clear transactional data in production without the opt-in flag', function () {
    app()->detectEnvironment(fn () => 'production');
    config()->set('app.allow_system_reset', false);

    expect(fn () => (new SystemResetService)->clearTransactionalData())
        ->toThrow(ApiException::class);
});
