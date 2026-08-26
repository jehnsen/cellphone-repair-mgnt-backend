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
        $cebu = Branch::query()->orderBy('id')->skip(1)->first();

        $this->makeUser($main, 'Ricardo Santos', 'owner');

        $this->makeUser($main, 'Cristina Bautista', 'manager');
        $this->makeUser($cebu, 'Eduardo Villanueva', 'manager');

        $this->makeUser($main, 'Angelica Reyes', 'cashier');
        $this->makeUser($main, 'Jomar Cruz', 'cashier');
        $this->makeUser($cebu, 'Rowena Mendoza', 'cashier');

        $this->makeUser($main, 'Kevin Ocampo', 'technician');
        $this->makeUser($cebu, 'Marites Torres', 'technician');
    }

    private function makeUser(Branch $branch, string $name, string $role): User
    {
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@fixmo.test',
        ]);

        $user->assignRole($role);

        return $user;
    }
}
