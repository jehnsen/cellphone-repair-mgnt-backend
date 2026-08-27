<?php

namespace App\Policies;

use App\Models\User;

class AcquisitionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('acquisitions.manage');
    }

    public function view(User $user): bool
    {
        return $user->can('acquisitions.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('acquisitions.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('acquisitions.manage');
    }
}
