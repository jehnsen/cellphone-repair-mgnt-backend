<?php

namespace Database\Seeders;

use App\Models\InstallmentPlan;
use App\Models\InstallmentSchedule;
use App\Models\Sale;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class InstallmentSeeder extends Seeder
{
    public function run(): void
    {
        $sales = Sale::query()->inRandomOrder()->limit(5)->get();

        foreach ($sales as $index => $sale) {
            $termMonths = 6;
            $principal = (float) $sale->total;
            $downpayment = round($principal * 0.2, 2);
            $monthly = round(($principal - $downpayment) / $termMonths, 2);

            $plan = InstallmentPlan::factory()->create([
                'sale_id' => $sale->id,
                'principal' => $principal,
                'downpayment' => $downpayment,
                'term_months' => $termMonths,
                'status' => 'active',
            ]);

            for ($month = 1; $month <= $termMonths; $month++) {
                $dueDate = $sale->created_at->clone()->addMonths($month);
                $isPast = $dueDate->isPast();
                // First two plans have a couple of overdue installments;
                // the rest are current.
                $isOverdue = $index < 2 && $isPast && $month <= 2;

                InstallmentSchedule::factory()->create([
                    'installment_plan_id' => $plan->id,
                    'due_date' => $dueDate,
                    'amount_due' => $monthly,
                    'amount_paid' => $isPast && ! $isOverdue ? $monthly : 0,
                    'status' => match (true) {
                        $isOverdue => 'overdue',
                        $isPast => 'paid',
                        default => 'pending',
                    },
                ]);
            }
        }
    }
}
