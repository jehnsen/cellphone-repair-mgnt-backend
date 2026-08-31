<?php

use App\Models\Acquisition;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\Product;
use App\Models\SerializedUnit;
use App\Models\Shift;

/** A completed buy-back whose offered_price is the trade-in credit. */
function completedAcquisition(Branch $branch, float $offeredPrice): Acquisition
{
    $unit = SerializedUnit::factory()->create(['branch_id' => $branch->id]);

    return Acquisition::factory()->create([
        'branch_id' => $branch->id,
        'offered_price' => $offeredPrice,
        'resulting_serialized_unit_id' => $unit->id,
    ]);
}

it('applies a completed acquisition as a trade-in payment on a sale', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = userWithRole('manager', $branch);
    $shift = Shift::factory()->open()->create(['branch_id' => $branch->id, 'cashier_id' => $user->id]);
    $product = Product::factory()->accessory()->create(['selling_price' => 5000, 'track_inventory' => false]);
    $acquisition = completedAcquisition($branch, 3000);

    $sale = $this->withToken($token)->postJson('/api/v1/sales', [
        'lines' => [['sellable_type' => 'product', 'product_ulid' => $product->ulid, 'quantity' => 1]],
    ])->assertStatus(201);
    $saleUlid = $sale->json('data.ulid');

    $this->withToken($token)->postJson("/api/v1/sales/{$saleUlid}/payments", [
        'method' => 'trade_in',
        'amount' => 3000,
        'acquisition_ulid' => $acquisition->ulid,
    ])->assertStatus(201)
        ->assertJsonPath('data.method', 'trade_in')
        ->assertJsonPath('data.acquisition_ulid', $acquisition->ulid);

    // trade_in never touches the drawer.
    expect(CashMovement::where('shift_id', $shift->id)->count())->toBe(0);
});

it('rejects a trade-in against an acquisition that is not completed', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = userWithRole('manager', $branch);
    Shift::factory()->open()->create(['branch_id' => $branch->id, 'cashier_id' => $user->id]);
    $product = Product::factory()->accessory()->create(['selling_price' => 5000, 'track_inventory' => false]);
    $acquisition = Acquisition::factory()->create(['branch_id' => $branch->id, 'offered_price' => 3000]);

    $sale = $this->withToken($token)->postJson('/api/v1/sales', [
        'lines' => [['sellable_type' => 'product', 'product_ulid' => $product->ulid, 'quantity' => 1]],
    ])->assertStatus(201);
    $saleUlid = $sale->json('data.ulid');

    $this->withToken($token)->postJson("/api/v1/sales/{$saleUlid}/payments", [
        'method' => 'trade_in',
        'amount' => 3000,
        'acquisition_ulid' => $acquisition->ulid,
    ])->assertStatus(409)->assertJsonPath('error.code', 'TRADE_IN_NOT_AVAILABLE');
});

it('rejects applying the same trade-in to a second sale', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = userWithRole('manager', $branch);
    Shift::factory()->open()->create(['branch_id' => $branch->id, 'cashier_id' => $user->id]);
    $product = Product::factory()->accessory()->create(['selling_price' => 5000, 'track_inventory' => false]);
    $acquisition = completedAcquisition($branch, 3000);

    $makeSale = fn () => $this->withToken($token)->postJson('/api/v1/sales', [
        'lines' => [['sellable_type' => 'product', 'product_ulid' => $product->ulid, 'quantity' => 1]],
    ])->assertStatus(201)->json('data.ulid');

    $first = $makeSale();
    $this->withToken($token)->postJson("/api/v1/sales/{$first}/payments", [
        'method' => 'trade_in', 'amount' => 3000, 'acquisition_ulid' => $acquisition->ulid,
    ])->assertStatus(201);

    $second = $makeSale();
    $this->withToken($token)->postJson("/api/v1/sales/{$second}/payments", [
        'method' => 'trade_in', 'amount' => 3000, 'acquisition_ulid' => $acquisition->ulid,
    ])->assertStatus(409)->assertJsonPath('error.code', 'TRADE_IN_NOT_AVAILABLE');
});

it('rejects a trade-in amount above the acquisition offered price', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = userWithRole('manager', $branch);
    Shift::factory()->open()->create(['branch_id' => $branch->id, 'cashier_id' => $user->id]);
    $product = Product::factory()->accessory()->create(['selling_price' => 5000, 'track_inventory' => false]);
    $acquisition = completedAcquisition($branch, 3000);

    $sale = $this->withToken($token)->postJson('/api/v1/sales', [
        'lines' => [['sellable_type' => 'product', 'product_ulid' => $product->ulid, 'quantity' => 1]],
    ])->assertStatus(201);
    $saleUlid = $sale->json('data.ulid');

    $this->withToken($token)->postJson("/api/v1/sales/{$saleUlid}/payments", [
        'method' => 'trade_in',
        'amount' => 4000,
        'acquisition_ulid' => $acquisition->ulid,
    ])->assertStatus(409)->assertJsonPath('error.code', 'TRADE_IN_NOT_AVAILABLE');
});

it('requires acquisition_ulid when method is trade_in', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = userWithRole('manager', $branch);
    Shift::factory()->open()->create(['branch_id' => $branch->id, 'cashier_id' => $user->id]);
    $product = Product::factory()->accessory()->create(['selling_price' => 5000, 'track_inventory' => false]);

    $sale = $this->withToken($token)->postJson('/api/v1/sales', [
        'lines' => [['sellable_type' => 'product', 'product_ulid' => $product->ulid, 'quantity' => 1]],
    ])->assertStatus(201);
    $saleUlid = $sale->json('data.ulid');

    $this->withToken($token)->postJson("/api/v1/sales/{$saleUlid}/payments", [
        'method' => 'trade_in',
        'amount' => 3000,
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
});
