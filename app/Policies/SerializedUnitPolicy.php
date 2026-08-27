<?php

namespace App\Policies;

use App\Models\User;

class SerializedUnitPolicy
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
        return $user->can('inventory.receive');
    }

    public function update(User $user): bool
    {
        return $user->can('inventory.receive');
    }
}
