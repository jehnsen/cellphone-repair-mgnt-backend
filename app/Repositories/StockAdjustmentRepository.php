<?php

namespace App\Repositories;

use App\Models\StockAdjustment;
use App\Repositories\Contracts\StockAdjustmentRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StockAdjustmentRepository extends BaseRepository implements StockAdjustmentRepositoryInterface
{
    protected function modelClass(): string
    {
        return StockAdjustment::class;
    }

    protected function filteredQuery(): QueryBuilder
    {
        // creator (User) is branch-scoped; whoever posted the adjustment may
        // not share the viewer's branch — see RepairTicketService::loadDisplayRelations().
        return parent::filteredQuery()->with([
            'lines.product',
            'creator' => fn ($query) => $query->withoutGlobalScopes(),
        ]);
    }

    protected function allowedFilters(): array
    {
        return [
            'reason_code',
            AllowedFilter::exact('created_by'),
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
