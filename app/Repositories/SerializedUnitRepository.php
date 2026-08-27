<?php

namespace App\Repositories;

use App\Models\SerializedUnit;
use App\Repositories\Contracts\SerializedUnitRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SerializedUnitRepository extends BaseRepository implements SerializedUnitRepositoryInterface
{
    protected function modelClass(): string
    {
        return SerializedUnit::class;
    }

    protected function filteredQuery(): QueryBuilder
    {
        return parent::filteredQuery()->with('product');
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('status'),
            AllowedFilter::exact('product_id'),
            AllowedFilter::exact('condition'),
            'imei',
            'serial_number',
        ];
    }

    protected function allowedSorts(): array
    {
        return ['created_at', 'status'];
    }

    protected function defaultSort(): string
    {
        return '-created_at';
    }
}
