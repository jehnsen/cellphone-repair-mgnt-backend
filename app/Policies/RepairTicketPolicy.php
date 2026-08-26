<?php

namespace App\Policies;

use App\Models\RepairTicket;
use App\Models\User;

class RepairTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tickets.view');
    }

    public function view(User $user): bool
    {
        return $user->can('tickets.view');
    }

    public function create(User $user): bool
    {
        return $user->can('tickets.create');
    }

    public function update(User $user): bool
    {
        return $user->can('tickets.update');
    }

    /** Releasing a unit needs its own permission — everything else just needs tickets.update. */
    public function transitionTo(User $user, RepairTicket $ticket, string $toStatus): bool
    {
        return $toStatus === 'released' ? $user->can('tickets.release') : $user->can('tickets.update');
    }
}
