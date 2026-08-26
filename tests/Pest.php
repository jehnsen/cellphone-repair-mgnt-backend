<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Creates a user with the given role (permissions synced via the real
 * seeder, not hand-rolled, so tests exercise the actual permission set) and
 * returns [$user, $bearerToken].
 */
function userWithRole(string $role, ?App\Models\Branch $branch = null): array
{
    // RefreshDatabase rolls back the transaction after every test, but
    // Spatie's permission cache ('array' store) is a process-lifetime
    // singleton that survives the rollback — without this, a later test
    // can see a previous test's now-rolled-back role/permission rows.
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    test()->seed(Database\Seeders\RoleAndPermissionSeeder::class);

    $branch ??= App\Models\Branch::factory()->create();
    $user = App\Models\User::factory()->create(['branch_id' => $branch->id]);
    $user->assignRole($role);

    return [$user, $user->createToken('test-device')->plainTextToken];
}
