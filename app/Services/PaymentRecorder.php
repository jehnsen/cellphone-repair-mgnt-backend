<?php

namespace App\Services;

use App\Models\Acquisition;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\RepairTicket;
use App\Models\Sale;
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
 *
 * Two methods carry extra settlement beyond the row itself:
 *  - `store_credit` — debits the payer's shop-wide store-credit ledger
 *    (StoreCreditService); requires the sale/ticket to name a customer.
 *  - `trade_in` — links a completed buy-back Acquisition whose
 *    `offered_price` caps the credit; each acquisition backs at most one.
 */
class PaymentRecorder
{
    public function __construct(private readonly StoreCreditService $storeCredit) {}

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

        $acquisitionId = null;
        if ($data['method'] === 'trade_in') {
            $acquisitionId = $this->resolveTradeInAcquisition($data, $amount, $actor)->id;
        }

        $payment = Payment::create([
            'payable_type' => $payableType,
            'payable_id' => $payableId,
            'method' => $data['method'],
            'amount' => $amount,
            'reference_number' => $data['reference_number'] ?? null,
            'tendered' => $tendered,
            'change_given' => $changeGiven,
            'shift_id' => $shift?->id,
            'acquisition_id' => $acquisitionId,
            'actor_id' => $actor->id,
        ]);

        if ($data['method'] === 'store_credit') {
            $this->redeemStoreCredit($payableType, $payableId, $amount, $payment, $actor);
        }

        return $payment;
    }

    private function redeemStoreCredit(string $payableType, int $payableId, float $amount, Payment $payment, User $actor): void
    {
        $customerId = $this->resolveCustomerId($payableType, $payableId);

        if ($customerId === null) {
            throw new ApiException(
                ErrorCode::ValidationFailed,
                'Store credit can only be used when the sale or ticket is linked to a customer.',
            );
        }

        /** @var Customer $customer */
        $customer = Customer::withoutGlobalScopes()->findOrFail($customerId);

        $this->storeCredit->redeem($customer, $amount, 'sale_payment', $actor, 'payment', $payment->id);
    }

    private function resolveTradeInAcquisition(array $data, float $amount, User $actor): Acquisition
    {
        $acquisitionId = $data['acquisition_id'] ?? null;

        if ($acquisitionId === null) {
            throw new ApiException(ErrorCode::ValidationFailed, 'A trade-in payment requires an acquisition_ulid.');
        }

        /** @var Acquisition|null $acquisition */
        $acquisition = Acquisition::withoutGlobalScopes()->find($acquisitionId);

        if ($acquisition === null || $acquisition->branch_id !== $actor->branch_id) {
            throw new ApiException(ErrorCode::TradeInNotAvailable, 'That acquisition is not available at this branch.');
        }

        if ($acquisition->resulting_serialized_unit_id === null) {
            throw new ApiException(
                ErrorCode::TradeInNotAvailable,
                'That acquisition has not been completed — finish intake and the IMEI check first.',
            );
        }

        if (Payment::where('acquisition_id', $acquisition->id)->exists()) {
            throw new ApiException(ErrorCode::TradeInNotAvailable, 'That trade-in has already been applied to a sale.');
        }

        if ($amount - (float) $acquisition->offered_price > 0.01) {
            throw new ApiException(
                ErrorCode::TradeInNotAvailable,
                sprintf('Trade-in value is %.2f; cannot apply %.2f.', (float) $acquisition->offered_price, $amount),
            );
        }

        return $acquisition;
    }

    private function resolveCustomerId(string $payableType, int $payableId): ?int
    {
        return match ($payableType) {
            'sale' => Sale::withoutGlobalScopes()->whereKey($payableId)->value('customer_id'),
            'repair_ticket' => RepairTicket::withoutGlobalScopes()->whereKey($payableId)->value('customer_id'),
            default => null,
        };
    }
}
