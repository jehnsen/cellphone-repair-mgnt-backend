<?php

namespace App\Repositories;

use App\Models\DeviceModel;
use App\Repositories\Contracts\DeviceModelRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DeviceModelRepository extends BaseRepository implements DeviceModelRepositoryInterface
{
    protected function modelClass(): string
    {
        return DeviceModel::class;
    }

    protected function allowedFilters(): array
    {
        return [
            'name',
            AllowedFilter::exact('device_brand_id'),
            AllowedFilter::exact('is_active'),
        ];
    }

    protected function allowedSorts(): array
    {
        return ['name', 'release_year', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }

    protected function filteredQuery(): QueryBuilder
    {
        return parent::filteredQuery()->with('brand');
    }
}
