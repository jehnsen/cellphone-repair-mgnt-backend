<?php

namespace App\Repositories;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    protected function modelClass(): string
    {
        return Product::class;
    }

    protected function allowedFilters(): array
    {
        return [
            'name',
            'sku',
            AllowedFilter::exact('type'),
            AllowedFilter::exact('product_category_id'),
            AllowedFilter::exact('device_brand_id'),
            AllowedFilter::exact('is_active'),
            AllowedFilter::exact('is_serialized'),
        ];
    }

    protected function allowedSorts(): array
    {
        return ['name', 'sku', 'cost', 'selling_price', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }

    protected function filteredQuery(): QueryBuilder
    {
        return parent::filteredQuery()
            ->with(['category', 'brand'])
            ->allowedIncludes('compatibleDeviceModels');
    }

    public function findBySku(string $sku): ?Product
    {
        return Product::where('sku', $sku)->first();
    }
}
