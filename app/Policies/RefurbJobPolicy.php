<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksBranchCapabilities;

/**
 * Refurb is bench work — putting parts into a bought-back unit — so it
 * needs a repair branch, even though buy-back *acquisition* itself is a
 * retail-counter action and stays open everywhere.
 */
class RefurbJobPolicy
{
    use ChecksBranchCapabilities;

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
        return $user->can('acquisitions.manage') && $this->branchOffersRepairs($user);
    }

    public function complete(User $user): bool
    {
        return $user->can('acquisitions.manage') && $this->branchOffersRepairs($user);
    }
}
