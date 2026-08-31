<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\StoreCreditAccount;
use App\Models\StoreCreditEntry;
use App\Models\User;
use App\Support\Api\ApiException;
use App\Support\Api\ErrorCode;
use Illuminate\Support\Facades\DB;

/**
 * The one place that writes `store_credit_entries` (append-only) and
 * updates `store_credit_accounts.balance` (cached, never authoritative) —
 * same lock-then-read-then-write shape as StockMovementRecorder and
 * Sequence::next(). Store credit is shop-wide: one account per customer,
 * usable at any branch.
 *
 * Issued from: a `store_credit` refund (RefundService), a manual manager
 * adjustment (StoreCreditController). Redeemed by: a `store_credit` payment
 * against a sale or repair ticket (PaymentRecorder).
 */
class StoreCreditService
{
    public function accountFor(Customer $customer): StoreCreditAccount
    {
        return StoreCreditAccount::firstOrCreate(
            ['customer_id' => $customer->id],
            ['balance' => 0],
        );
    }

    public function issue(
        Customer $customer,
        float $amount,
        string $reason,
        User $actor,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): StoreCreditEntry {
        return $this->apply($customer, 'credit', $amount, $reason, $referenceType, $referenceId, $actor);
    }

    public function redeem(
        Customer $customer,
        float $amount,
        string $reason,
        User $actor,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): StoreCreditEntry {
        return $this->apply($customer, 'debit', $amount, $reason, $referenceType, $referenceId, $actor);
    }

    private function apply(
        Customer $customer,
        string $direction,
        float $amount,
        string $reason,
        ?string $referenceType,
        ?int $referenceId,
        User $actor,
    ): StoreCreditEntry {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new ApiException(ErrorCode::ValidationFailed, 'A store-credit amount must be greater than zero.');
        }

        // Ensure the row exists before we take the lock — customer_id is
        // unique, so a concurrent create just loses the race harmlessly.
        $this->accountFor($customer);

        return DB::transaction(function () use ($customer, $direction, $amount, $reason, $referenceType, $referenceId, $actor) {
            /** @var StoreCreditAccount $account */
            $account = StoreCreditAccount::where('customer_id', $customer->id)->lockForUpdate()->firstOrFail();
            $balance = (float) $account->balance;

            if ($direction === 'debit') {
                if ($amount - $balance > 0.01) {
                    throw new ApiException(
                        ErrorCode::InsufficientStoreCredit,
                        sprintf('Store-credit balance is %.2f; this would draw %.2f.', $balance, $amount),
                    );
                }
                $newBalance = round($balance - $amount, 2);
            } else {
                $newBalance = round($balance + $amount, 2);
            }

            $account->update(['balance' => $newBalance]);

            return StoreCreditEntry::create([
                'store_credit_account_id' => $account->id,
                'direction' => $direction,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reason' => $reason,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'actor_id' => $actor->id,
            ]);
        });
    }
}
