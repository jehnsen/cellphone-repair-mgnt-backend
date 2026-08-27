<?php

namespace App\Policies;

use App\Models\User;

class InstallmentPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sales.create') || $user->can('reports.view');
    }

    public function view(User $user): bool
    {
        return $user->can('sales.create') || $user->can('reports.view');
    }

    public function create(User $user): bool
    {
        return $user->can('sales.create');
    }

    public function pay(User $user): bool
    {
        return $user->can('sales.create');
    }
}
