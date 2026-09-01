<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $main = Branch::query()->orderBy('id')->first();
        $main = Branch::query()->orderBy('id')->skip(1)->first();

        $this->makeUser($main, 'Nelson Bonalos', 'owner');
        $this->makeUser($main, 'Amylou Bonalos', 'manager');
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
