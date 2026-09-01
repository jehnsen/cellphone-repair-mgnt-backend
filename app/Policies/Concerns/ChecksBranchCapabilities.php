<?php

namespace App\Policies\Concerns;

use App\Models\User;

/**
 * A `sales_only` branch has no repair bench, so the whole repair surface
 * (job orders, the status board, findings, part swaps, refurb jobs) is
 * closed to staff working there — regardless of what their role permits.
 *
 * This is a second gate, not a replacement for the permission check: a
 * cashier at a repair branch may create a job order, the same cashier
 * transferred to the retail branch may not. Both must pass.
 */
trait ChecksBranchCapabilities
{
    /** False when the actor's own branch doesn't do repairs. */
    protected function branchOffersRepairs(User $user): bool
    {
        // No branch at all (a system/console actor) isn't restricted here —
        // BranchScope already governs what such a caller can see.
        return $user->branch?->offersRepairs() ?? true;
    }
}
