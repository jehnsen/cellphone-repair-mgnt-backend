<?php

namespace App\Policies;

use App\Models\User;

class SupplierReturnPolicy
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
        return $user->can('supplier_returns.manage');
    }

    public function close(User $user): bool
    {
        return $user->can('supplier_returns.manage');
    }
}
