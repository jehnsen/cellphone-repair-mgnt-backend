<?php

namespace App\Policies;

use App\Models\RepairTicket;
use App\Models\User;
use App\Policies\Concerns\ChecksBranchCapabilities;

class RepairTicketPolicy
{
    use ChecksBranchCapabilities;

    // Reading is not gated on branch type: an owner reviewing the retail
    // branch, or a cashier who moved between branches, can still look at
    // an existing ticket. Only *writing* new repair work is closed off.
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
        return $user->can('tickets.create') && $this->branchOffersRepairs($user);
    }

    public function update(User $user): bool
    {
        return $user->can('tickets.update') && $this->branchOffersRepairs($user);
    }

    /** Releasing a unit needs its own permission — everything else just needs tickets.update. */
    public function transitionTo(User $user, RepairTicket $ticket, string $toStatus): bool
    {
        if (! $this->branchOffersRepairs($user)) {
            return false;
        }

        return $toStatus === 'released' ? $user->can('tickets.release') : $user->can('tickets.update');
    }

    public function verifyImei(User $user, RepairTicket $ticket): bool
    {
        return $user->can('tickets.update') && $this->branchOffersRepairs($user);
    }

    /** The owner-only escape hatch for a mismatched or unreadable IMEI. */
    public function overrideImei(User $user, RepairTicket $ticket): bool
    {
        return $user->can('tickets.imei_override');
    }

    public function recordPartSwap(User $user, RepairTicket $ticket): bool
    {
        return $user->can('tickets.update') && $this->branchOffersRepairs($user);
    }

    public function viewFinding(User $user, RepairTicket $ticket): bool
    {
        return $user->can('tickets.view');
    }

    /** Cashiers run the counter at a small shop, so they record findings too. */
    public function recordFinding(User $user, RepairTicket $ticket): bool
    {
        return $user->can('tickets.update') && $this->branchOffersRepairs($user);
    }

    /** Collecting money is a POS action even when it's against a ticket, not a sale. */
    public function recordPayment(User $user, RepairTicket $ticket): bool
    {
        return $user->can('sales.create');
    }
}
