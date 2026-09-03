<?php

namespace App\Policies;

use App\Models\User;

class SaleWarrantyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sales_warranty.view');
    }

    public function view(User $user): bool
    {
        return $user->can('sales_warranty.view');
    }
}
