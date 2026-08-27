<?php

namespace App\Repositories;

use App\Models\StockLevel;
use App\Repositories\Contracts\StockLevelRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StockLevelRepository extends BaseRepository implements StockLevelRepositoryInterface
{
    protected function modelClass(): string
    {
        return StockLevel::class;
    }

    protected function filteredQuery(): QueryBuilder
    {
        return parent::filteredQuery()->with('product');
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('product_id'),
        ];
    }

    protected function allowedSorts(): array
    {
        return ['on_hand_qty', 'updated_at'];
    }

    protected function defaultSort(): string
    {
        return 'product_id';
    }
}
