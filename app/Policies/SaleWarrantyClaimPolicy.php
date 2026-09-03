<?php

namespace App\Policies;

use App\Models\User;

class SaleWarrantyClaimPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sales_warranty.view');
    }

    public function view(User $user): bool
    {
        return $user->can('sales_warranty.view');
    }

    public function create(User $user): bool
    {
        return $user->can('sales_warranty.manage');
    }

    public function resolve(User $user): bool
    {
        return $user->can('sales_warranty.manage');
    }
}
