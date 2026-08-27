<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Shift;
use App\Models\User;
use App\Support\Api\ApiException;
use App\Support\Api\ErrorCode;

/**
 * The one place that writes `payments` (append-only — Payment::UPDATED_AT
 * is null; a correction is a new, signed, reversing row). Shared by
 * SaleService (payable_type=sale) and RepairTicketService
 * (payable_type=repair_ticket), since both are just "apply money toward a
 * balance" with the same cash-tendered/change-given and overpayment rules.
 */
class PaymentRecorder
{
    public function record(
        string $payableType,
        int $payableId,
        float $amountOwed,
        float $alreadyPaid,
        array $data,
        User $actor,
        ?Shift $shift,
    ): Payment {
        $amount = round((float) $data['amount'], 2);
        $remaining = round($amountOwed - $alreadyPaid, 2);

        if ($amount - $remaining > 0.01) {
            throw new ApiException(
                ErrorCode::PaymentSumMismatch,
                sprintf('This payment of %.2f exceeds the %.2f still owed.', $amount, max($remaining, 0)),
            );
        }

        $tendered = null;
        $changeGiven = null;

        if ($data['method'] === 'cash') {
            $tendered = round((float) ($data['tendered'] ?? $amount), 2);
            $changeGiven = round($tendered - $amount, 2);
        }

        return Payment::create([
            'payable_type' => $payableType,
            'payable_id' => $payableId,
            'method' => $data['method'],
            'amount' => $amount,
            'reference_number' => $data['reference_number'] ?? null,
            'tendered' => $tendered,
            'change_given' => $changeGiven,
            'shift_id' => $shift?->id,
            'actor_id' => $actor->id,
        ]);
    }
}
