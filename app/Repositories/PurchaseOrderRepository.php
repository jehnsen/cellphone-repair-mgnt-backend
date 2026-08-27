<?php

namespace App\Repositories;

use App\Models\PurchaseOrder;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PurchaseOrderRepository extends BaseRepository implements PurchaseOrderRepositoryInterface
{
    protected function modelClass(): string
    {
        return PurchaseOrder::class;
    }

    protected function filteredQuery(): QueryBuilder
    {
        return parent::filteredQuery()->with(['supplier', 'lines.product']);
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('status'),
            AllowedFilter::exact('supplier_id'),
        ];
    }

    protected function allowedSorts(): array
    {
        return ['created_at', 'expected_date'];
    }

    protected function defaultSort(): string
    {
        return '-created_at';
    }
}
