<?php

namespace Database\Factories;

use App\Support\PhilippineFaker;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\NotificationLog> */
class NotificationLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'recipient' => PhilippineFaker::mobile(),
            'channel' => 'sms',
            'message_template_id' => null,
            'rendered_body' => 'Your device is ready for pickup.',
            'status' => 'sent',
            'provider_reference' => null,
            'error' => null,
            'created_at' => now(),
        ];
    }
}
