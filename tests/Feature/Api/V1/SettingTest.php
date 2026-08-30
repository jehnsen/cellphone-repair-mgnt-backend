<?php

use App\Models\Branch;
use App\Models\Setting;

it('resolves global defaults with a branch override winning', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('owner', $branch);

    Setting::factory()->global()->pair('bir.display_on_receipt', true, 'bool')->create();
    Setting::factory()->global()->pair('pos.round_to_nearest_centavo', true, 'bool')->create();
    Setting::factory()->pair('bir.display_on_receipt', false, 'bool')->create(['branch_id' => $branch->id]);

    $response = $this->withToken($token)->getJson('/api/v1/settings')->assertOk();

    $rows = collect($response->json('data'))->keyBy('key');

    expect($rows['bir.display_on_receipt']['value'])->toBeFalse()
        ->and($rows['bir.display_on_receipt']['source'])->toBe('branch')
        ->and($rows['pos.round_to_nearest_centavo']['value'])->toBeTrue()
        ->and($rows['pos.round_to_nearest_centavo']['source'])->toBe('global');
});

it('bulk-upserts only the keys in the payload against the caller branch', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('owner', $branch);

    Setting::factory()->global()->pair('a.flag', true, 'bool')->create();
    Setting::factory()->global()->pair('b.flag', true, 'bool')->create();

    $this->withToken($token)->putJson('/api/v1/settings', [
        'settings' => [
            'a.flag' => false,
            'c.note' => 'hello',
        ],
    ])->assertOk();

    // a.flag overridden for this branch, b.flag untouched (still global only),
    // c.note created for this branch.
    expect(Setting::where('branch_id', $branch->id)->where('key', 'a.flag')->value('value'))->toBeFalse()
        ->and(Setting::where('branch_id', $branch->id)->where('key', 'b.flag')->exists())->toBeFalse()
        ->and(Setting::where('branch_id', $branch->id)->where('key', 'c.note')->value('value'))->toBe('hello');
});

it('infers the type tag from the value when none is given', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('owner', $branch);

    $this->withToken($token)->putJson('/api/v1/settings', [
        'settings' => [
            'x.int' => 7,
            'x.bool' => true,
            'x.str' => 'yo',
            'x.json' => ['nested' => 1],
        ],
    ])->assertOk();

    $rows = Setting::where('branch_id', $branch->id)->pluck('type', 'key');

    expect($rows['x.int'])->toBe('int')
        ->and($rows['x.bool'])->toBe('bool')
        ->and($rows['x.str'])->toBe('string')
        ->and($rows['x.json'])->toBe('json');
});

it('accepts the object form with an explicit type', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('owner', $branch);

    $this->withToken($token)->putJson('/api/v1/settings', [
        'settings' => [
            'price.threshold' => ['value' => '199.00', 'type' => 'decimal'],
        ],
    ])->assertOk()->assertJsonPath('data.0.type', 'decimal');
});

it('deletes a branch override when the value is null, falling back to global', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('owner', $branch);

    Setting::factory()->global()->pair('a.flag', true, 'bool')->create();
    Setting::factory()->pair('a.flag', false, 'bool')->create(['branch_id' => $branch->id]);

    $response = $this->withToken($token)->putJson('/api/v1/settings', [
        'settings' => ['a.flag' => null],
    ])->assertOk();

    expect(Setting::where('branch_id', $branch->id)->where('key', 'a.flag')->exists())->toBeFalse();

    $row = collect($response->json('data'))->firstWhere('key', 'a.flag');
    expect($row['value'])->toBeTrue()->and($row['source'])->toBe('global');
});

it('rejects a key longer than 100 characters', function () {
    [, $token] = userWithRole('owner');

    $this->withToken($token)->putJson('/api/v1/settings', [
        'settings' => [str_repeat('k', 101) => 'v'],
    ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
});

it('rejects a tagged entry with an invalid type', function () {
    [, $token] = userWithRole('owner');

    $this->withToken($token)->putJson('/api/v1/settings', [
        'settings' => ['a.flag' => ['value' => true, 'type' => 'weird']],
    ])->assertStatus(422)->assertJsonPath('error.details.0.field', 'settings.a.flag.type');
});

it('treats an array without a value key as a literal json value', function () {
    $branch = Branch::factory()->create();
    [, $token] = userWithRole('owner', $branch);

    $this->withToken($token)->putJson('/api/v1/settings', [
        'settings' => ['a.config' => ['type' => 'bool', 'foo' => 1]],
    ])->assertOk();

    $row = Setting::where('branch_id', $branch->id)->where('key', 'a.config')->sole();
    expect($row->type)->toBe('json')
        ->and($row->value)->toEqualCanonicalizing(['type' => 'bool', 'foo' => 1]);
});

it('forbids a manager without settings.manage from reading settings', function () {
    [, $token] = userWithRole('manager');

    $this->withToken($token)->getJson('/api/v1/settings')->assertStatus(403);
});

it('forbids a cashier from writing settings', function () {
    [, $token] = userWithRole('cashier');

    $this->withToken($token)->putJson('/api/v1/settings', [
        'settings' => ['a.flag' => true],
    ])->assertStatus(403);
});

it('scopes reads and writes to the callers own branch', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    [, $tokenA] = userWithRole('owner', $branchA);

    Setting::factory()->pair('secret', 'B-only', 'string')->create(['branch_id' => $branchB->id]);

    $this->withToken($tokenA)->putJson('/api/v1/settings', [
        'settings' => ['secret' => 'A-value'],
    ])->assertOk();

    expect(Setting::where('branch_id', $branchA->id)->where('key', 'secret')->value('value'))->toBe('A-value')
        ->and(Setting::where('branch_id', $branchB->id)->where('key', 'secret')->value('value'))->toBe('B-only');
});
