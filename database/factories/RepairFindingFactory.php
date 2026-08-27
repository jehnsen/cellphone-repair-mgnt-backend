<?php

namespace Database\Factories;

use App\Models\RepairFinding;
use App\Models\RepairTicket;
use App\Models\User;
use App\Support\RepairFinding\Defect;
use App\Support\RepairFinding\Resolution;
use App\Support\RepairFinding\RootCause;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RepairFinding> */
class RepairFindingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'repair_ticket_id' => RepairTicket::factory(),
            'summary' => fake()->sentence(),
            'details' => fake()->optional()->paragraph(),
            'root_cause' => fake()->randomElement(RootCause::values()),
            'defects' => fake()->randomElements(Defect::values(), 2),
            'resolution' => fake()->randomElement(Resolution::values()),
            'technician_notes' => fake()->optional()->sentence(),
            'qc_passed' => null,
            'qc_checked_at' => null,
            'qc_checked_by_id' => null,
            'recorded_by_id' => User::factory(),
        ];
    }
}
