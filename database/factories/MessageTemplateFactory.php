<?php

namespace Database\Factories;

use App\Models\MessageTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MessageTemplate> */
class MessageTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'channel' => fake()->randomElement(MessageTemplate::CHANNELS),
            'event_key' => fake()->unique()->randomElement(MessageTemplate::EVENT_KEYS),
            'body' => 'Hi {{customer_name}}, your device (ticket {{ticket_number}}) update: {{message}}.',
            'is_active' => true,
        ];
    }
}
