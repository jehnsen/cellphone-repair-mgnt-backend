<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleWarranty;
use App\Models\SerializedUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SaleWarranty> */
class SaleWarrantyFactory extends Factory
{
    public function definition(): array
    {
        $days = fake()->randomElement([180, 365, 730]);
        $startsAt = now()->subDays(fake()->numberBetween(0, 60));
        $branch = Branch::factory()->create();
        $unit = SerializedUnit::factory()->for($branch, 'branch')->sold()->create();
        $sale = Sale::factory()->for($branch, 'branch')->create();
        $line = SaleLine::factory()->create([
            'sale_id' => $sale->id,
            'sellable_type' => 'serialized_unit',
            'sellable_id' => $unit->id,
        ]);

        return [
            'branch_id' => $branch->id,
            'sale_id' => $sale->id,
            'sale_line_id' => $line->id,
            'serialized_unit_id' => $unit->id,
            'customer_id' => null,
            'coverage' => fake()->randomElement(['shop', 'manufacturer']),
            'term_days' => $days,
            'starts_at' => $startsAt->toDateString(),
            'expiry_date' => $startsAt->clone()->addDays($days)->toDateString(),
            'warranty_code' => 'SW-'.fake()->unique()->numerify('######'),
            'terms' => null,
            'exclusions' => 'Does not cover physical or liquid damage.',
            'voided_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subDays(400)->toDateString(),
            'expiry_date' => now()->subDays(35)->toDateString(),
            'term_days' => 365,
        ]);
    }

    public function voided(): static
    {
        return $this->state(fn () => ['voided_at' => now()]);
    }
}
