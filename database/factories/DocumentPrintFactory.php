<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\DocumentPrint> */
class DocumentPrintFactory extends Factory
{
    public function definition(): array
    {
        return [
            'document_type' => 'claim_stub',
            'printable_type' => 'repair_ticket',
            'printable_id' => 1,
            'kind' => 'original',
            'sequence_no' => 1,
            'printed_by' => User::factory(),
            'printed_at' => now(),
        ];
    }
}
