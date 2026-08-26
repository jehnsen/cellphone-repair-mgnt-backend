<?php

namespace App\Repositories\Contracts;

use App\Models\Customer;

interface CustomerRepositoryInterface extends RepositoryInterface
{
    public function findByMobile(int $branchId, string $mobile): ?Customer;
}
