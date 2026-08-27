<?php

namespace App\Policies;

use App\Models\User;

class StockLevelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.view');
    }
}
