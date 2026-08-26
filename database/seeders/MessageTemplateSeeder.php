<?php

namespace Database\Seeders;

use App\Models\MessageTemplate;
use Illuminate\Database\Seeder;

class MessageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            ['sms', 'ticket.ready_for_pickup', 'Hi {{customer_name}}, your {{device_model}} (JO# {{ticket_number}}) is ready for pickup at FixMo {{branch_name}}.'],
            ['sms', 'ticket.unclaimed_30', 'Hi {{customer_name}}, your device (JO# {{ticket_number}}) has been ready for pickup for 30 days. Please claim it soon.'],
            ['sms', 'ticket.unclaimed_60', 'Reminder: your device (JO# {{ticket_number}}) has been unclaimed for 60 days. Please visit FixMo {{branch_name}} to claim it.'],
            ['sms', 'ticket.unclaimed_90', 'Final notice: your device (JO# {{ticket_number}}) has been unclaimed for 90 days. Please contact FixMo {{branch_name}} immediately.'],
            ['viber', 'warranty.expiring_soon', 'Hi {{customer_name}}, the warranty on your repair (JO# {{ticket_number}}) expires on {{expiry_date}}.'],
            ['sms', 'installment.due_reminder', 'Hi {{customer_name}}, your installment payment of {{amount_due}} is due on {{due_date}}.'],
        ])->each(fn (array $t) => MessageTemplate::factory()->create([
            'channel' => $t[0], 'event_key' => $t[1], 'body' => $t[2],
        ]));
    }
}
