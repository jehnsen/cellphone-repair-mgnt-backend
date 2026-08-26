<?php

namespace App\Repositories;

use App\Models\RepairTicket;
use App\Repositories\Contracts\RepairTicketRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class RepairTicketRepository extends BaseRepository implements RepairTicketRepositoryInterface
{
    protected function modelClass(): string
    {
        return RepairTicket::class;
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::exact('status'),
            AllowedFilter::exact('assigned_technician_id'),
            AllowedFilter::exact('customer_id'),
            'ticket_number',
            'claim_code',
            AllowedFilter::callback('device_brand_id', fn (Builder $query, $value) => $query
                ->whereHas('customerDevice.deviceModel', fn (Builder $q) => $q->where('device_brand_id', $value))),
            AllowedFilter::callback('search', function (Builder $query, $value) {
                $query->where(function (Builder $q) use ($value): void {
                    $q->where('ticket_number', 'like', "%{$value}%")
                        ->orWhere('claim_code', 'like', "%{$value}%")
                        ->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'like', "%{$value}%")->orWhere('mobile', 'like', "%{$value}%"))
                        ->orWhereHas('customerDevice', fn (Builder $c) => $c->where('imei_normalized', 'like', "%{$value}%"));
                });
            }),
            AllowedFilter::callback('promised_from', fn (Builder $query, $value) => $query->whereDate('promised_date', '>=', $value)),
            AllowedFilter::callback('promised_to', fn (Builder $query, $value) => $query->whereDate('promised_date', '<=', $value)),
            AllowedFilter::callback('overdue', function (Builder $query, $value) {
                if ($value) {
                    $query->whereDate('promised_date', '<', now())
                        ->whereNotIn('status', ['released', 'returned_as_is', 'unrepairable']);
                }
            }),
        ];
    }

    protected function allowedSorts(): array
    {
        return ['created_at', 'promised_date', 'status', 'ticket_number'];
    }

    protected function defaultSort(): string
    {
        return '-created_at';
    }

    protected function filteredQuery(): QueryBuilder
    {
        // A ticket's own branch_id gates listing visibility; its customer or
        // technician may legitimately sit in another branch — see
        // RepairTicketService::loadDisplayRelations().
        return parent::filteredQuery()->with([
            'customer' => fn ($query) => $query->withoutGlobalScopes(),
            'customerDevice.deviceModel',
            'assignedTechnician' => fn ($query) => $query->withoutGlobalScopes(),
        ]);
    }
}
