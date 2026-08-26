<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Spatie\QueryBuilder\QueryBuilder;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    protected function modelClass(): string
    {
        return User::class;
    }

    protected function filteredQuery(): QueryBuilder
    {
        return parent::filteredQuery()->with('branch');
    }

    protected function allowedFilters(): array
    {
        return ['name', 'employee_code', 'email', 'branch_id', 'is_active'];
    }

    protected function allowedSorts(): array
    {
        return ['name', 'employee_code', 'created_at', 'last_login_at'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }
}
