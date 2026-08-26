<?php

namespace App\Repositories;

use App\Models\DeviceBrand;
use App\Repositories\Contracts\DeviceBrandRepositoryInterface;

class DeviceBrandRepository extends BaseRepository implements DeviceBrandRepositoryInterface
{
    protected function modelClass(): string
    {
        return DeviceBrand::class;
    }

    protected function allowedFilters(): array
    {
        return ['name', 'is_active'];
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
