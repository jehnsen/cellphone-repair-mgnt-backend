<?php

namespace App\Repositories;

use App\Models\ProductCategory;
use App\Repositories\Contracts\ProductCategoryRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProductCategoryRepository extends BaseRepository implements ProductCategoryRepositoryInterface
{
    protected function modelClass(): string
    {
        return ProductCategory::class;
    }

    protected function filteredQuery(): QueryBuilder
    {
        return parent::filteredQuery()->with('parent');
    }

    protected function allowedFilters(): array
    {
        return ['name', AllowedFilter::exact('parent_id'), 'is_active'];
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
