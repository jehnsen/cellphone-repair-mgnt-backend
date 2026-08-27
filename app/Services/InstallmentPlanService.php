<?php

namespace App\Services;

use App\Models\InstallmentPlan;
use App\Models\InstallmentSchedule;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\User;
use App\Repositories\Contracts\InstallmentPlanRepositoryInterface;
use App\Support\Api\ApiException;
use App\Support\Api\ErrorCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InstallmentPlanService
{
    public function __construct(
        private readonly InstallmentPlanRepositoryInterface $plans,
        private readonly PaymentRecorder $payments,
    ) {}

    public function list(): LengthAwarePaginator
    {
        return $this->plans->paginate();
    }

    /**
     * Splits (principal − downpayment) evenly across term_months, one
     * schedule row per month starting a month after the sale — the last
     * row absorbs the rounding remainder so the schedule always sums
     * exactly back to principal − downpayment.
     */
    public function create(Sale $sale, array $data): InstallmentPlan
    {
        return DB::transaction(function () use ($sale, $data) {
            $principal = (float) $sale->total;
            $downpayment = (float) ($data['downpayment'] ?? 0);
            $termMonths = (int) $data['term_months'];
            $financed = round($principal - $downpayment, 2);
            $monthly = round($financed / $termMonths, 2);

            $plan = $this->plans->create([
                'sale_id' => $sale->id,
                'principal' => $principal,
                'downpayment' => $downpayment,
                'term_months' => $termMonths,
                'schedule_rule' => 'monthly',
                'status' => 'active',
            ]);

            $runningTotal = 0.0;
            for ($month = 1; $month <= $termMonths; $month++) {
                $isLast = $month === $termMonths;
                $amount = $isLast ? round($financed - $runningTotal, 2) : $monthly;
                $runningTotal = round($runningTotal + $amount, 2);

                InstallmentSchedule::create([
                    'installment_plan_id' => $plan->id,
                    'due_date' => $sale->created_at->clone()->addMonths($month)->toDateString(),
                    'amount_due' => $amount,
                    'amount_paid' => 0,
                    'status' => 'pending',
                ]);
            }

            return $plan->fresh(['sale', 'schedules']);
        });
    }

    public function show(InstallmentPlan $plan): InstallmentPlan
    {
        return $plan->load(['sale', 'schedules']);
    }

    /**
     * A schedule payment is also a real Payment row against the underlying
     * sale (payable_type=sale) — keeps the shop's one unified payments
     * ledger accurate for reporting, rather than installments living in a
     * parallel bookkeeping system.
     */
    public function pay(InstallmentPlan $plan, InstallmentSchedule $schedule, array $data, User $actor, ?Shift $shift): InstallmentSchedule
    {
        return DB::transaction(function () use ($plan, $schedule, $data, $actor, $shift) {
            $remaining = round((float) $schedule->amount_due - (float) $schedule->amount_paid, 2);

            if ($remaining <= 0) {
                throw new ApiException(ErrorCode::InvalidStatusTransition, 'This installment is already fully paid.');
            }

            $this->payments->record('sale', $plan->sale_id, (float) $schedule->amount_due, (float) $schedule->amount_paid, $data, $actor, $shift);

            $amountPaid = round((float) $schedule->amount_paid + (float) $data['amount'], 2);
            $schedule->update([
                'amount_paid' => $amountPaid,
                'status' => $amountPaid >= (float) $schedule->amount_due - 0.01 ? 'paid' : $schedule->status,
            ]);

            $this->syncPlanStatus($plan);

            return $schedule->fresh();
        });
    }

    private function syncPlanStatus(InstallmentPlan $plan): void
    {
        $allPaid = $plan->schedules()->where('status', '!=', 'paid')->doesntExist();

        if ($allPaid) {
            $plan->update(['status' => 'completed']);
        }
    }
}
