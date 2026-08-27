<?php

namespace App\Repositories\Contracts;

use App\Models\Shift;
use App\Models\User;

interface ShiftRepositoryInterface extends RepositoryInterface
{
    public function findOpenFor(User $cashier): ?Shift;
}
