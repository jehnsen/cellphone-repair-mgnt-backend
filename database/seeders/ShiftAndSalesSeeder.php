<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\Sequence;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/** 90 days of sales and a full shift history per branch, per the Testing §. */
class ShiftAndSalesSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::all();
        $sellables = Product::where('track_inventory', true)->where('type', '!=', 'handset')->get();

        foreach ($branches as $branch) {
            $cashiers = User::role('cashier')->where('branch_id', $branch->id)->get();
            if ($cashiers->isEmpty()) {
                $cashiers = User::role('cashier')->get();
            }

            for ($daysAgo = 89; $daysAgo >= 0; $daysAgo--) {
                $businessDate = Carbon::now()->subDays($daysAgo);
                $cashier = $cashiers->random();

                $openedAt = $businessDate->clone()->setTime(9, 0);
                $closedAt = $businessDate->clone()->setTime(18, 0);
                $isToday = $daysAgo === 0;

                $openingFloat = 2000;
                $shift = Shift::factory()->create([
                    'branch_id' => $branch->id,
                    'cashier_id' => $cashier->id,
                    'opened_at' => $openedAt,
                    'opening_float' => $openingFloat,
                    'closed_at' => $isToday ? null : $closedAt,
                    'counted_cash' => $isToday ? null : null,
                    'expected_cash' => $isToday ? null : null,
                    'variance' => $isToday ? null : null,
                    'created_at' => $openedAt,
                    'updated_at' => $isToday ? $openedAt : $closedAt,
                ]);

                $cashTotal = $openingFloat;

                foreach (range(1, random_int(2, 8)) as $__) {
                    $saleAt = $openedAt->clone()->addMinutes(random_int(0, 540));
                    $cashTotal += $this->makeSale($branch, $shift, $cashier, $sellables, $saleAt)->total;
                }

                if (! $isToday) {
                    $expected = round($cashTotal, 2);
                    $counted = round($expected + fake()->randomFloat(2, -50, 50), 2);

                    $shift->update([
                        'expected_cash' => $expected,
                        'counted_cash' => $counted,
                        'variance' => round($counted - $expected, 2),
                    ]);

                    if (fake()->boolean(15)) {
                        CashMovement::factory()->create([
                            'shift_id' => $shift->id,
                            'direction' => 'out',
                            'amount' => fake()->randomFloat(2, 100, 1000),
                            'reason' => 'Bank deposit',
                            'actor_id' => $cashier->id,
                            'created_at' => $closedAt->clone()->subMinutes(10),
                        ]);
                    }
                }
            }
        }
    }

    private function makeSale(Branch $branch, Shift $shift, User $cashier, $sellables, Carbon $at): Sale
    {
        $lineCount = random_int(1, 3);
        $subtotal = 0;
        $lines = [];

        for ($i = 0; $i < $lineCount; $i++) {
            $product = $sellables->random();
            $qty = random_int(1, 2);
            $amount = round($product->selling_price * $qty, 2);
            $subtotal += $amount;

            $lines[] = ['product' => $product, 'qty' => $qty, 'amount' => $amount];
        }

        $vat = round($subtotal / 1.12 * 0.12, 2);

        $sale = Sale::factory()->create([
            'branch_id' => $branch->id,
            'sale_number' => $this->saleNumber($branch, $at),
            'cashier_id' => $cashier->id,
            'shift_id' => $shift->id,
            'subtotal' => $subtotal,
            'discount_total' => 0,
            'vat_amount' => $vat,
            'vatable_sales' => round($subtotal - $vat, 2),
            'total' => $subtotal,
            'status' => 'completed',
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        foreach ($lines as $line) {
            SaleLine::query()->create([
                'sale_id' => $sale->id,
                'sellable_type' => 'product',
                'sellable_id' => $line['product']->id,
                'quantity' => $line['qty'],
                'unit_price' => $line['product']->selling_price,
                'unit_cost' => $line['product']->cost,
                'line_discount' => 0,
                'amount' => $line['amount'],
            ]);
        }

        Payment::factory()->create([
            'payable_type' => 'sale',
            'payable_id' => $sale->id,
            'method' => fake()->randomElement(['cash', 'cash', 'cash', 'gcash', 'maya', 'card']),
            'amount' => $sale->total,
            'tendered' => $sale->total,
            'change_given' => 0,
            'shift_id' => $shift->id,
            'actor_id' => $cashier->id,
            'created_at' => $at,
        ]);

        return $sale;
    }

    private function saleNumber(Branch $branch, Carbon $at): string
    {
        $n = Sequence::next($branch->id, 'sale', (int) $at->format('Y'), (int) $at->format('m'));

        return sprintf('SI-%s-%s-%04d', $branch->code, $at->format('Ym'), $n);
    }
}
