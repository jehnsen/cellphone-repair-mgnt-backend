<?php

namespace App\Services;

use App\Models\Branch;
use App\Repositories\Contracts\BranchRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BranchService
{
    public function __construct(private readonly BranchRepositoryInterface $branches) {}

    public function list(): LengthAwarePaginator
    {
        return $this->branches->paginate();
    }

    public function create(array $data): Branch
    {
        return $this->branches->create($data);
    }

    public function update(Branch $branch, array $data): Branch
    {
        return $this->branches->update($branch, $data);
    }

    // No delete(): branches aren't soft-deletable (Rule 8 doesn't list them)
    // and a hard delete would be blocked by RESTRICT the moment any user,
    // customer, etc. references it anyway. Closing a branch is `is_active
    // = false` via update(), a business event — not a row removal.
}
