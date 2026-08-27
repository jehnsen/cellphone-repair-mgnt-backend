<?php

namespace App\Repositories;

use App\Models\Sale;
use App\Repositories\Contracts\SaleRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SaleRepository extends BaseRepository implements SaleRepositoryInterface
{
    protected function modelClass(): string
    {
        return Sale::class;
    }

    protected function filteredQuery(): QueryBuilder
    {
        return parent::filteredQuery()->with([
            'customer',
            'cashier' => fn ($query) => $query->withoutGlobalScopes(),
        ]);
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('status'),
            AllowedFilter::exact('cashier_id'),
            AllowedFilter::exact('shift_id'),
            'sale_number',
            AllowedFilter::callback('created_from', fn (Builder $query, $value) => $query->whereDate('created_at', '>=', $value)),
            AllowedFilter::callback('created_to', fn (Builder $query, $value) => $query->whereDate('created_at', '<=', $value)),
        ];
    }

    protected function allowedSorts(): array
    {
        return ['created_at', 'total'];
    }

    protected function defaultSort(): string
    {
        return '-created_at';
    }
}
