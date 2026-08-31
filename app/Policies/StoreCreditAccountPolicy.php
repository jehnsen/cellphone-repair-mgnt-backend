<?php

namespace App\Policies;

use App\Models\User;

class StoreCreditAccountPolicy
{
    public function view(User $user): bool
    {
        return $user->can('customers.view');
    }

    public function adjust(User $user): bool
    {
        return $user->can('store_credit.manage');
    }
}
