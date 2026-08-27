<?php

namespace App\Repositories;

use App\Models\Supplier;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;

class SupplierRepository extends BaseRepository implements SupplierRepositoryInterface
{
    protected function modelClass(): string
    {
        return Supplier::class;
    }

    protected function allowedFilters(): array
    {
        return [
            'name',
            AllowedFilter::exact('is_active'),
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
}
