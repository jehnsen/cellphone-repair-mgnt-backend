<?php

namespace App\Repositories;

use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CustomerRepository extends BaseRepository implements CustomerRepositoryInterface
{
    protected function modelClass(): string
    {
        return Customer::class;
    }

    protected function filteredQuery(): QueryBuilder
    {
        return parent::filteredQuery()->with('branch');
    }

    protected function allowedFilters(): array
    {
        return [
            'name',
            'mobile',
            AllowedFilter::exact('branch_id'),
            AllowedFilter::exact('is_blacklisted'),
        ];
    }

    protected function allowedSorts(): array
    {
        return ['name', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }

    public function findByMobile(int $branchId, string $mobile): ?Customer
    {
        return Customer::where('branch_id', $branchId)->where('mobile', $mobile)->first();
    }
}
