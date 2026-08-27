<?php

namespace App\Repositories\Contracts;

use Illuminate\Pagination\CursorPaginator;

interface StockMovementRepositoryInterface extends RepositoryInterface
{
    /** Cursor-paginated per Rule ("cursor pagination on ledger/timeline endpoints") — see TicketEvent's equivalent. */
    public function ledger(int $perPage = 20): CursorPaginator;
}
