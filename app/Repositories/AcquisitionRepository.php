<?php

namespace App\Repositories;

use App\Models\Acquisition;
use App\Repositories\Contracts\AcquisitionRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AcquisitionRepository extends BaseRepository implements AcquisitionRepositoryInterface
{
    protected function modelClass(): string
    {
        return Acquisition::class;
    }

    protected function filteredQuery(): QueryBuilder
    {
        return parent::filteredQuery()->with([
            'processor' => fn ($query) => $query->withoutGlobalScopes(),
            'resultingSerializedUnit',
        ]);
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('imei_check_result'),
            'imei',
            'seller_name',
        ];
    }

    protected function allowedSorts(): array
    {
        return ['created_at', 'purchase_date'];
    }

    protected function defaultSort(): string
    {
        return '-created_at';
    }
}
