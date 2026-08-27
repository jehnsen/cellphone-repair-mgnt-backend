<?php

namespace App\Repositories;

use App\Models\RefurbJob;
use App\Repositories\Contracts\RefurbJobRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class RefurbJobRepository extends BaseRepository implements RefurbJobRepositoryInterface
{
    protected function modelClass(): string
    {
        return RefurbJob::class;
    }

    protected function filteredQuery(): QueryBuilder
    {
        return parent::filteredQuery()->with(['acquisition', 'serializedUnit.product', 'lines.product']);
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('status'),
        ];
    }

    protected function allowedSorts(): array
    {
        return ['created_at'];
    }

    protected function defaultSort(): string
    {
        return '-created_at';
    }
}
