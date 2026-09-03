<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\RepairTicket;
use App\Models\SaleWarranty;
use App\Models\SerializedUnit;
use App\Models\Shift;
use App\Models\Supplier;

/** A cashier with an open shift at $branch, ready to ring up a sale. */
function warrantyCashier(Branch $branch): array
{
    [$user, $token] = userWithRole('cashier', $branch);
    Shift::factory()->open()->create(['branch_id' => $branch->id, 'cashier_id' => $user->id]);

    return [$user, $token];
}

/** Rings up one serialized unit and returns the sale ULID. */
function sellUnit(string $token, SerializedUnit $unit, array $lineExtra = []): string
{
    return test()->withToken($token)->postJson('/api/v1/sales', [
        'lines' => [
            ['sellable_type' => 'serialized_unit', 'serialized_unit_ulid' => $unit->ulid] + $lineExtra,
        ],
    ])->assertStatus(201)->json('data.ulid');
}

it('auto-issues a sale warranty when a unit with a catalog warranty term is sold', function () {
    $branch = Branch::factory()->create();
    [, $token] = warrantyCashier($branch);
    $product = Product::factory()->handset()->create(['warranty_days' => 365, 'selling_price' => 11200]);
    $unit = SerializedUnit::factory()->create(['branch_id' => $branch->id, 'product_id' => $product->id]);

    $saleUlid = sellUnit($token, $unit);

    $warranties = $this->withToken($token)->getJson("/api/v1/sales/{$saleUlid}/warranties")->assertOk();
    expect($warranties->json('data'))->toHaveCount(1);

    $warranty = $warranties->json('data.0');
    expect($warranty['coverage'])->toBe('shop')
        ->and($warranty['term_days'])->toBe(365)
        ->and($warranty['is_active'])->toBeTrue()
        ->and($warranty['warranty_code'])->toStartWith('SW-')
        ->and($warranty['starts_at'])->toBe(now()->toDateString())
        ->and($warranty['expiry_date'])->toBe(now()->copy()->startOfDay()->addDays(365)->toDateString());
});

it('issues no warranty when the product carries no warranty term', function () {
    $branch = Branch::factory()->create();
    [, $token] = warrantyCashier($branch);
    $product = Product::factory()->handset()->create(['warranty_days' => 0]);
    $unit = SerializedUnit::factory()->create(['branch_id' => $branch->id, 'product_id' => $product->id]);

    $saleUlid = sellUnit($token, $unit);

    $this->withToken($token)->getJson("/api/v1/sales/{$saleUlid}/warranties")
        ->assertOk()->assertJsonCount(0, 'data');
});

it('lets the cashier override the warranty term and coverage on the line', function () {
    $branch = Branch::factory()->create();
    [, $token] = warrantyCashier($branch);
    $product = Product::factory()->handset()->create(['warranty_days' => 365]);
    $unit = SerializedUnit::factory()->create(['branch_id' => $branch->id, 'product_id' => $product->id]);

    $saleUlid = sellUnit($token, $unit, ['warranty_days' => 730, 'warranty_coverage' => 'manufacturer']);

    $warranty = $this->withToken($token)->getJson("/api/v1/sales/{$saleUlid}/warranties")->assertOk()->json('data.0');
    expect($warranty['term_days'])->toBe(730)->and($warranty['coverage'])->toBe('manufacturer');
});

it('voids the issued warranty when the sale is voided', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = warrantyCashier($branch);
    $user->assignRole('manager');
    $product = Product::factory()->handset()->create(['warranty_days' => 365]);
    $unit = SerializedUnit::factory()->create(['branch_id' => $branch->id, 'product_id' => $product->id]);
    $saleUlid = sellUnit($token, $unit);

    $this->withToken($token)->postJson("/api/v1/sales/{$saleUlid}/void", [
        'void_reason' => 'Wrong unit rung up at the counter entirely.',
    ])->assertOk();

    $warranty = $this->withToken($token)->getJson("/api/v1/sales/{$saleUlid}/warranties")->assertOk()->json('data.0');
    expect($warranty['is_active'])->toBeFalse()->and($warranty['voided_at'])->not->toBeNull();
});

it('files a warranty claim that stays under CP units, creating no repair ticket', function () {
    $branch = Branch::factory()->create();
    [, $token] = warrantyCashier($branch);
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);
    $product = Product::factory()->handset()->create(['warranty_days' => 365]);
    $unit = SerializedUnit::factory()->create(['branch_id' => $branch->id, 'product_id' => $product->id]);
    $saleUlid = sellUnit($token, $unit);
    $warrantyUlid = $this->withToken($token)->getJson("/api/v1/sales/{$saleUlid}/warranties")->json('data.0.ulid');

    $before = RepairTicket::count();

    $claim = $this->withToken($token)->postJson("/api/v1/sale-warranties/{$warrantyUlid}/claims", [
        'reported_defect' => 'Screen flickers and the battery drains overnight.',
    ])->assertStatus(201);

    $claim->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.handling', 'separate')
        ->assertJsonPath('data.within_coverage', true)
        ->assertJsonPath('data.repair_ticket_ulid', null);

    expect(RepairTicket::count())->toBe($before);
});

it('optionally links an existing job order to a claim', function () {
    $branch = Branch::factory()->create();
    [, $token] = warrantyCashier($branch);
    $product = Product::factory()->handset()->create(['warranty_days' => 365]);
    $unit = SerializedUnit::factory()->create(['branch_id' => $branch->id, 'product_id' => $product->id]);
    $ticket = RepairTicket::factory()->create(['branch_id' => $branch->id]);
    $saleUlid = sellUnit($token, $unit);
    $warrantyUlid = $this->withToken($token)->getJson("/api/v1/sales/{$saleUlid}/warranties")->json('data.0.ulid');

    $this->withToken($token)->postJson("/api/v1/sale-warranties/{$warrantyUlid}/claims", [
        'reported_defect' => 'Charging port loose.',
        'handling' => 'repair_board',
        'repair_ticket_ulid' => $ticket->ulid,
    ])->assertStatus(201)
        ->assertJsonPath('data.handling', 'repair_board')
        ->assertJsonPath('data.repair_ticket_ulid', $ticket->ulid);
});

it('flags a claim outside coverage but still records it', function () {
    $branch = Branch::factory()->create();
    [, $token] = warrantyCashier($branch);
    $warranty = SaleWarranty::factory()->expired()->create(['branch_id' => $branch->id]);

    $this->withToken($token)->postJson("/api/v1/sale-warranties/{$warranty->ulid}/claims", [
        'reported_defect' => 'Speaker dead, well past the warranty window.',
    ])->assertStatus(201)->assertJsonPath('data.within_coverage', false);
});

it('resolves a claim in place', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = warrantyCashier($branch);
    $user->assignRole('manager');
    $warranty = SaleWarranty::factory()->create(['branch_id' => $branch->id]);
    $claimUlid = $this->withToken($token)->postJson("/api/v1/sale-warranties/{$warranty->ulid}/claims", [
        'reported_defect' => 'Rebooting on its own.',
    ])->json('data.ulid');

    $this->withToken($token)->postJson("/api/v1/sale-warranty-claims/{$claimUlid}/resolve", [
        'resolution' => 'repaired_in_house',
        'outcome_notes' => 'Reflashed firmware, tested 24h.',
    ])->assertOk()
        ->assertJsonPath('data.status', 'resolved')
        ->assertJsonPath('data.resolution', 'repaired_in_house');
});

it('sends a sold defective unit back to the supplier and moves it to returned_to_supplier', function () {
    $branch = Branch::factory()->create();
    [, $token] = warrantyCashier($branch);
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->handset()->create(['warranty_days' => 365]);
    $unit = SerializedUnit::factory()->create(['branch_id' => $branch->id, 'product_id' => $product->id]);
    $saleUlid = sellUnit($token, $unit);
    $warrantyUlid = $this->withToken($token)->getJson("/api/v1/sales/{$saleUlid}/warranties")->json('data.0.ulid');
    $claimUlid = $this->withToken($token)->postJson("/api/v1/sale-warranties/{$warrantyUlid}/claims", [
        'reported_defect' => 'Factory defect — dead pixels out of the box.',
    ])->json('data.ulid');

    $this->withToken($token)->postJson('/api/v1/supplier-returns', [
        'serialized_unit_ulid' => $unit->ulid,
        'supplier_ulid' => $supplier->ulid,
        'sale_warranty_claim_ulid' => $claimUlid,
        'reason' => 'factory_defect',
    ])->assertStatus(201)->assertJsonPath('data.status', 'sent');

    expect($unit->fresh()->status)->toBe('returned_to_supplier');
});

it('closes a supplier return as replaced, minting a unit and resolving the linked claim', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = warrantyCashier($branch);
    $user->assignRole('manager');
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->handset()->create(['warranty_days' => 365]);
    $unit = SerializedUnit::factory()->create(['branch_id' => $branch->id, 'product_id' => $product->id]);
    $saleUlid = sellUnit($token, $unit);
    $warrantyUlid = $this->withToken($token)->getJson("/api/v1/sales/{$saleUlid}/warranties")->json('data.0.ulid');
    $claimUlid = $this->withToken($token)->postJson("/api/v1/sale-warranties/{$warrantyUlid}/claims", [
        'reported_defect' => 'DOA.',
    ])->json('data.ulid');
    $returnUlid = $this->withToken($token)->postJson('/api/v1/supplier-returns', [
        'serialized_unit_ulid' => $unit->ulid,
        'supplier_ulid' => $supplier->ulid,
        'sale_warranty_claim_ulid' => $claimUlid,
        'reason' => 'factory_defect',
    ])->json('data.ulid');

    $this->withToken($token)->postJson("/api/v1/supplier-returns/{$returnUlid}/close", [
        'outcome' => 'replaced',
        'replacement' => ['imei' => '490154203237518', 'condition' => 'brand_new'],
    ])->assertOk()
        ->assertJsonPath('data.status', 'replaced')
        ->assertJsonPath('data.replacement_serialized_unit.status', 'in_stock');

    $this->withToken($token)->getJson("/api/v1/sale-warranty-claims/{$claimUlid}")
        ->assertOk()
        ->assertJsonPath('data.status', 'resolved')
        ->assertJsonPath('data.resolution', 'returned_to_supplier');
});

it('closes a supplier return as credited, recording the amount for margin holders', function () {
    $branch = Branch::factory()->create();
    [$user, $token] = warrantyCashier($branch);
    $user->assignRole('owner');
    $supplier = Supplier::factory()->create();
    $unit = SerializedUnit::factory()->create(['branch_id' => $branch->id]);

    $returnUlid = $this->withToken($token)->postJson('/api/v1/supplier-returns', [
        'serialized_unit_ulid' => $unit->ulid,
        'supplier_ulid' => $supplier->ulid,
        'reason' => 'dead_on_arrival',
    ])->assertStatus(201)->json('data.ulid');

    $this->withToken($token)->postJson("/api/v1/supplier-returns/{$returnUlid}/close", [
        'outcome' => 'credited',
        'credit_amount' => 8500,
    ])->assertOk()
        ->assertJsonPath('data.status', 'credited')
        ->assertJsonPath('data.credit_amount', '8500.00');
});

it('rejects sending a written-off unit back to a supplier', function () {
    $branch = Branch::factory()->create();
    [, $token] = warrantyCashier($branch);
    $supplier = Supplier::factory()->create();
    $unit = SerializedUnit::factory()->create(['branch_id' => $branch->id, 'status' => 'written_off']);

    $this->withToken($token)->postJson('/api/v1/supplier-returns', [
        'serialized_unit_ulid' => $unit->ulid,
        'supplier_ulid' => $supplier->ulid,
        'reason' => 'other',
    ])->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATUS_TRANSITION');
});

it('forbids a technician from filing a warranty claim', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('technician', $branch);
    $warranty = SaleWarranty::factory()->create(['branch_id' => $branch->id]);

    $this->withToken($token)->postJson("/api/v1/sale-warranties/{$warranty->ulid}/claims", [
        'reported_defect' => 'Should not be allowed.',
    ])->assertStatus(403);
});
