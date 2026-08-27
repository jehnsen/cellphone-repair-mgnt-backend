<?php

namespace App\Repositories;

use App\Models\StockMovement;
use App\Repositories\Contracts\StockMovementRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StockMovementRepository extends BaseRepository implements StockMovementRepositoryInterface
{
    protected function modelClass(): string
    {
        return StockMovement::class;
    }

    protected function filteredQuery(): QueryBuilder
    {
        return parent::filteredQuery()->with(['product', 'serializedUnit', 'actor' => fn ($query) => $query->withoutGlobalScopes()]);
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('product_id'),
            AllowedFilter::exact('movement_type'),
            AllowedFilter::exact('reference_type'),
            AllowedFilter::callback('occurred_from', fn (Builder $query, $value) => $query->whereDate('occurred_at', '>=', $value)),
            AllowedFilter::callback('occurred_to', fn (Builder $query, $value) => $query->whereDate('occurred_at', '<=', $value)),
        ];
    }

    public function ledger(int $perPage = 20): CursorPaginator
    {
        return $this->filteredQuery()
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }
}
