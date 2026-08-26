<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\MessageTemplate> */
class MessageTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'channel' => fake()->randomElement(['sms', 'viber', 'email']),
            'event_key' => fake()->unique()->randomElement([
                'ticket.ready_for_pickup', 'ticket.unclaimed_30', 'ticket.unclaimed_60',
                'ticket.unclaimed_90', 'warranty.expiring_soon', 'installment.due_reminder',
            ]),
            'body' => 'Hi {{customer_name}}, your device (ticket {{ticket_number}}) update: {{message}}.',
            'is_active' => true,
        ];
    }
}
