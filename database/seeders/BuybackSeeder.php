<?php

namespace Database\Seeders;

use App\Models\Acquisition;
use App\Models\Branch;
use App\Models\Product;
use App\Models\RefurbJob;
use App\Models\RefurbJobLine;
use App\Models\SerializedUnit;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Seeder;

class BuybackSeeder extends Seeder
{
    public function run(): void
    {
        $handset = Product::where('type', 'handset')->inRandomOrder()->first();
        $part = Product::where('type', 'part')->inRandomOrder()->first();

        foreach (Branch::all() as $branch) {
            $processor = User::role('manager')->where('branch_id', $branch->id)->first()
                ?? User::query()->firstOrFail();

            // Two clear acquisitions, refurbished and put back into stock.
            for ($i = 0; $i < 2; $i++) {
                $acquisition = Acquisition::factory()->create([
                    'branch_id' => $branch->id,
                    'processed_by' => $processor->id,
                ]);

                $unit = SerializedUnit::factory()->create([
                    'product_id' => $handset->id,
                    'branch_id' => $branch->id,
                    'condition' => 'secondhand',
                    'acquisition_cost' => $acquisition->offered_price,
                    'acquisition_source' => 'buyback',
                ]);

                $acquisition->update(['resulting_serialized_unit_id' => $unit->id]);

                $movement = StockMovement::create([
                    'product_id' => $part->id,
                    'branch_id' => $branch->id,
                    'quantity' => -1,
                    'unit_cost' => $part->cost,
                    'movement_type' => 'ticket_consumption',
                    'reference_type' => 'refurb_job',
                    'reference_id' => 0,
                    'actor_id' => $processor->id,
                    'balance_after' => 0,
                    'occurred_at' => now(),
                ]);

                $job = RefurbJob::factory()->completed()->create([
                    'acquisition_id' => $acquisition->id,
                    'serialized_unit_id' => $unit->id,
                ]);

                $movement->update(['reference_id' => $job->id]);

                RefurbJobLine::factory()->create([
                    'refurb_job_id' => $job->id,
                    'product_id' => $part->id,
                    'stock_movement_id' => $movement->id,
                ]);
            }

            // One flagged acquisition — legally blocked from completion,
            // no resulting unit (see docs/design/01-domain-design.md §2.8).
            Acquisition::factory()->flagged()->create([
                'branch_id' => $branch->id,
                'processed_by' => $processor->id,
            ]);
        }
    }
}
