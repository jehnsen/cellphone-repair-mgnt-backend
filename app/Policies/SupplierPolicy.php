<?php

namespace App\Policies;

use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.view');
    }

    public function view(User $user): bool
    {
        return $user->can('inventory.view');
    }

    public function create(User $user): bool
    {
        return $user->can('suppliers.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('suppliers.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('suppliers.manage');
    }
}
