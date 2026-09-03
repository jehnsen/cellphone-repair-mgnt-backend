<?php

namespace App\Repositories;

use App\Models\SupplierReturn;
use App\Repositories\Contracts\SupplierReturnRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SupplierReturnRepository extends BaseRepository implements SupplierReturnRepositoryInterface
{
    protected function modelClass(): string
    {
        return SupplierReturn::class;
    }

    protected function filteredQuery(): QueryBuilder
    {
        return parent::filteredQuery()->with([
            'supplier',
            'serializedUnit.product',
            'replacementSerializedUnit',
        ]);
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('status'),
            AllowedFilter::exact('reason'),
            AllowedFilter::exact('supplier_id'),
            AllowedFilter::exact('serialized_unit_id'),
        ];
    }

    protected function allowedSorts(): array
    {
        return ['created_at', 'sent_at'];
    }
}
