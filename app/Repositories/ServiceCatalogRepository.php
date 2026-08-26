<?php

namespace App\Repositories;

use App\Models\Service;
use App\Repositories\Contracts\ServiceCatalogRepositoryInterface;

/**
 * Named "ServiceCatalog" rather than "Service" to avoid colliding with the
 * generic "service layer" naming convention used across App\Services.
 */
class ServiceCatalogRepository extends BaseRepository implements ServiceCatalogRepositoryInterface
{
    protected function modelClass(): string
    {
        return Service::class;
    }

    protected function allowedFilters(): array
    {
        return ['name', 'category', 'is_active'];
    }

    protected function allowedSorts(): array
    {
        return ['name', 'default_price', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }
}
