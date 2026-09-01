<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $main = Branch::query()->where('code', 'AL')->firstOrFail();
        $salesCenter = Branch::query()->where('code', 'SC')->first() ?? $main;

        // The owner sits at the main branch but is the only account holding
        // branches.view_all — the one login that can see both branches'
        // sales, repairs, and inventory together.
        $this->makeUser($main, 'Nelson Bonalos', 'owner');
        $this->makeUser($main, 'Amylou Bonalos', 'manager');

        // Retail-branch cashier: POS, job orders, the board, and the
        // limited dashboard — their own branch only.
        $this->makeUser($salesCenter, 'Jomar Cruz', 'cashier');
    }

    private function makeUser(Branch $branch, string $name, string $role): User
    {
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@gmail.com',
        ]);

        $user->assignRole($role);

        return $user;
    }
}
