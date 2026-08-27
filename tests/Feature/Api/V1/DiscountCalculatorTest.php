<?php

use App\Models\Branch;

it('previews a percent discount without creating anything', function () {
    $branch = Branch::factory()->create(['vat_registered' => true]);
    [, $token] = userWithRole('cashier', $branch);

    $this->withToken($token)->getJson('/api/v1/discounts/calculate?amount=1000&type=percent&value=10')
        ->assertOk()
        ->assertJsonPath('data.discount_total', '100.00')
        ->assertJsonPath('data.total', '900.00');
});

it('previews a senior citizen discount, defaulting the rate to 20%', function () {
    $branch = Branch::factory()->create(['vat_registered' => true]);
    [, $token] = userWithRole('cashier', $branch);

    $this->withToken($token)->getJson('/api/v1/discounts/calculate?amount=112&type=senior_citizen')
        ->assertOk()
        ->assertJsonPath('data.vat_amount', '0.00')
        ->assertJsonPath('data.vat_exempt_sales', '80.00')
        ->assertJsonPath('data.total', '80.00');
});

it('requires a value for a plain percent/amount discount', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('cashier', $branch);

    $this->withToken($token)->getJson('/api/v1/discounts/calculate?amount=1000&type=percent')
        ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
});
