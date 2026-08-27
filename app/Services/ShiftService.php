<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\User;
use App\Repositories\Contracts\ShiftRepositoryInterface;
use App\Support\Api\ApiException;
use App\Support\Api\ErrorCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ShiftService
{
    public function __construct(private readonly ShiftRepositoryInterface $shifts) {}

    public function list(): LengthAwarePaginator
    {
        return $this->shifts->paginate();
    }

    public function open(array $data, User $actor): Shift
    {
        if ($this->shifts->findOpenFor($actor) !== null) {
            throw new ApiException(ErrorCode::ValidationFailed, 'You already have an open shift — close it before opening a new one.');
        }

        return $this->shifts->create([
            'branch_id' => $actor->branch_id,
            'cashier_id' => $actor->id,
            'opened_at' => now(),
            'opening_float' => $data['opening_float'] ?? 0,
        ]);
    }

    /**
     * expected_cash only counts *cash* payments recorded during this shift
     * (Payment.shift_id) plus cash movements in/out — gcash/card/etc. never
     * touch the physical drawer. Refunds aren't wired into this yet
     * (deferred, see README) — a cash refund during an open shift won't
     * currently reduce expected_cash.
     */
    public function close(Shift $shift, array $data, User $actor): Shift
    {
        return DB::transaction(function () use ($shift, $data) {
            /** @var Shift $locked */
            $locked = Shift::whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw new ApiException(ErrorCode::ShiftNotOpen, 'This shift is already closed.');
            }

            $cashPayments = (float) Payment::where('shift_id', $locked->id)->where('method', 'cash')->sum('amount');
            $cashIn = (float) CashMovement::where('shift_id', $locked->id)->where('direction', 'in')->sum('amount');
            $cashOut = (float) CashMovement::where('shift_id', $locked->id)->where('direction', 'out')->sum('amount');

            $expected = round((float) $locked->opening_float + $cashPayments + $cashIn - $cashOut, 2);
            $counted = round((float) $data['counted_cash'], 2);

            $locked->update([
                'closed_at' => now(),
                'expected_cash' => $expected,
                'counted_cash' => $counted,
                'variance' => round($counted - $expected, 2),
                'notes' => $data['notes'] ?? $locked->notes,
            ]);

            return $locked->fresh();
        });
    }

    public function addCashMovement(Shift $shift, array $data, User $actor): CashMovement
    {
        if (! $shift->isOpen()) {
            throw new ApiException(ErrorCode::ShiftNotOpen, 'This shift is closed.');
        }

        return CashMovement::create([
            'shift_id' => $shift->id,
            'direction' => $data['direction'],
            'amount' => $data['amount'],
            'reason' => $data['reason'],
            'actor_id' => $actor->id,
        ]);
    }
}
