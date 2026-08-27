<?php

namespace App\Repositories;

use App\Models\Shift;
use App\Models\User;
use App\Repositories\Contracts\ShiftRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ShiftRepository extends BaseRepository implements ShiftRepositoryInterface
{
    protected function modelClass(): string
    {
        return Shift::class;
    }

    protected function filteredQuery(): QueryBuilder
    {
        return parent::filteredQuery()->with(['cashier' => fn ($query) => $query->withoutGlobalScopes()]);
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('cashier_id'),
            AllowedFilter::callback('is_open', fn ($query, $value) => $value
                ? $query->whereNull('closed_at')
                : $query->whereNotNull('closed_at')),
        ];
    }

    protected function allowedSorts(): array
    {
        return ['opened_at', 'closed_at'];
    }

    protected function defaultSort(): string
    {
        return '-opened_at';
    }

    public function findOpenFor(User $cashier): ?Shift
    {
        return Shift::where('cashier_id', $cashier->id)->whereNull('closed_at')->first();
    }
}
