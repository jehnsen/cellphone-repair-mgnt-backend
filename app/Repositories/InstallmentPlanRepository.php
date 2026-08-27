<?php

namespace App\Repositories;

use App\Models\InstallmentPlan;
use App\Repositories\Contracts\InstallmentPlanRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class InstallmentPlanRepository extends BaseRepository implements InstallmentPlanRepositoryInterface
{
    protected function modelClass(): string
    {
        return InstallmentPlan::class;
    }

    protected function filteredQuery(): QueryBuilder
    {
        return parent::filteredQuery()->with(['sale', 'schedules']);
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('status'),
            AllowedFilter::exact('sale_id'),
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
