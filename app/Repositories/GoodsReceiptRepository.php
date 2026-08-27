<?php

namespace App\Repositories;

use App\Models\GoodsReceipt;
use App\Repositories\Contracts\GoodsReceiptRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class GoodsReceiptRepository extends BaseRepository implements GoodsReceiptRepositoryInterface
{
    protected function modelClass(): string
    {
        return GoodsReceipt::class;
    }

    protected function filteredQuery(): QueryBuilder
    {
        return parent::filteredQuery()->with(['supplier', 'lines.product', 'receiver' => fn ($query) => $query->withoutGlobalScopes()]);
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('supplier_id'),
            AllowedFilter::exact('purchase_order_id'),
        ];
    }

    protected function allowedSorts(): array
    {
        return ['created_at', 'received_at'];
    }

    protected function defaultSort(): string
    {
        return '-received_at';
    }
}
