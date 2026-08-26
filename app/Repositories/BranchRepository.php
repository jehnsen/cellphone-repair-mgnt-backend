<?php

namespace App\Repositories;

use App\Models\Branch;
use App\Repositories\Contracts\BranchRepositoryInterface;

class BranchRepository extends BaseRepository implements BranchRepositoryInterface
{
    protected function modelClass(): string
    {
        return Branch::class;
    }

    protected function allowedFilters(): array
    {
        return ['name', 'code', 'city', 'is_active'];
    }

    protected function allowedSorts(): array
    {
        return ['name', 'code', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }
}
