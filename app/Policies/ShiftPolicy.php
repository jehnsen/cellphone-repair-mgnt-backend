<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\User;

class ShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('shifts.open') || $user->can('shifts.close');
    }

    public function view(User $user, Shift $shift): bool
    {
        return $user->id === $shift->cashier_id || $user->hasAnyRole(['owner', 'manager']);
    }

    public function open(User $user): bool
    {
        return $user->can('shifts.open');
    }

    /** A cashier closes their own drawer; owner/manager can close on anyone's behalf. */
    public function close(User $user, Shift $shift): bool
    {
        return $user->can('shifts.close') && ($user->id === $shift->cashier_id || $user->hasAnyRole(['owner', 'manager']));
    }

    public function addCashMovement(User $user, Shift $shift): bool
    {
        return ($user->can('shifts.open') || $user->can('shifts.close'))
            && ($user->id === $shift->cashier_id || $user->hasAnyRole(['owner', 'manager']));
    }
}
