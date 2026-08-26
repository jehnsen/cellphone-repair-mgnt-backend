<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\CustomerDevice;
use App\Models\ImeiVerification;
use App\Models\Payment;
use App\Models\RepairTicket;
use App\Models\Sequence;
use App\Models\Service;
use App\Models\TicketEvent;
use App\Models\TicketLine;
use App\Models\UnclaimedNotice;
use App\Models\User;
use App\Models\VerificationToken;
use App\Models\Warranty;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * 120 tickets spread across every status (docs/design/01-domain-design.md
 * Testing §), each with a plausible event trail, lines, and — for released
 * tickets — payment/warranty/chain-of-custody records to match.
 */
class RepairTicketSeeder extends Seeder
{
    /** status => how many */
    private const DISTRIBUTION = [
        'received' => 10,
        'diagnosed' => 10,
        'awaiting_approval' => 10,
        'awaiting_parts' => 8,
        'in_repair' => 12,
        'qc' => 8,
        'ready_for_pickup' => 10,
        'released' => 30,
        'unrepairable' => 5,
        'returned_as_is' => 5,
        'unclaimed' => 12,
    ];

    /** Statuses at or past diagnosis — a technician is already assigned. */
    private const ASSIGNED_STATUSES = [
        'diagnosed', 'awaiting_approval', 'awaiting_parts', 'in_repair',
        'qc', 'ready_for_pickup', 'released', 'unrepairable', 'unclaimed',
    ];

    public function run(): void
    {
        $devices = CustomerDevice::with('customer')->get();
        $servicesByBranch = Service::all();
        $fallbackActor = User::role('manager')->first() ?? User::query()->firstOrFail();

        foreach (self::DISTRIBUTION as $status => $count) {
            for ($i = 0; $i < $count; $i++) {
                $device = $devices->random();
                $branch = Branch::find($device->customer->branch_id);
                $technician = User::role('technician')->where('branch_id', $branch->id)->first()
                    ?? User::role('technician')->first();

                $receivedAt = Carbon::now()->subDays(random_int(1, 89))->setTime(random_int(9, 17), random_int(0, 59));

                $ticket = RepairTicket::factory()->create([
                    'branch_id' => $branch->id,
                    'customer_id' => $device->customer_id,
                    'customer_device_id' => $device->id,
                    'ticket_number' => $this->ticketNumber($branch, $receivedAt),
                    'assigned_technician_id' => in_array($status, self::ASSIGNED_STATUSES, true) ? $technician?->id : null,
                    'status' => $status,
                    'created_at' => $receivedAt,
                    'updated_at' => $receivedAt,
                ]);

                TicketEvent::factory()->create([
                    'repair_ticket_id' => $ticket->id,
                    'actor_id' => $technician?->id,
                    'event_type' => 'ticket_created',
                    'from_status' => null,
                    'to_status' => 'received',
                    'created_at' => $receivedAt,
                ]);

                if ($device->imei_normalized) {
                    ImeiVerification::factory()->create([
                        'repair_ticket_id' => $ticket->id,
                        'phase' => 'intake',
                        'scanned_imei' => $device->imei_normalized,
                        'actor_id' => $technician?->id ?? $fallbackActor->id,
                        'created_at' => $receivedAt,
                    ]);
                }

                VerificationToken::factory()->create(['repair_ticket_id' => $ticket->id]);

                $this->applyStatusHistory($ticket, $status, $receivedAt, $servicesByBranch, $technician);
            }
        }
    }

    private function applyStatusHistory(RepairTicket $ticket, string $status, Carbon $receivedAt, $services, ?User $technician): void
    {
        $trail = match ($status) {
            'received' => [],
            'diagnosed' => ['diagnosed'],
            'awaiting_approval' => ['diagnosed', 'awaiting_approval'],
            'awaiting_parts' => ['diagnosed', 'awaiting_approval', 'awaiting_parts'],
            'in_repair' => ['diagnosed', 'awaiting_approval', 'in_repair'],
            'qc' => ['diagnosed', 'awaiting_approval', 'in_repair', 'qc'],
            'ready_for_pickup' => ['diagnosed', 'awaiting_approval', 'in_repair', 'qc', 'ready_for_pickup'],
            'released' => ['diagnosed', 'awaiting_approval', 'in_repair', 'qc', 'ready_for_pickup', 'released'],
            'unrepairable' => ['diagnosed', 'unrepairable'],
            'returned_as_is' => ['diagnosed', 'awaiting_approval', 'returned_as_is'],
            'unclaimed' => ['diagnosed', 'awaiting_approval', 'in_repair', 'qc', 'ready_for_pickup', 'unclaimed'],
            default => [],
        };

        $cursor = $receivedAt->clone();
        $previous = 'received';

        foreach ($trail as $to) {
            $cursor = $cursor->clone()->addHours(random_int(2, 30));

            TicketEvent::factory()->create([
                'repair_ticket_id' => $ticket->id,
                'actor_id' => $technician?->id,
                'event_type' => 'status_changed',
                'from_status' => $previous,
                'to_status' => $to,
                'created_at' => $cursor,
            ]);

            $previous = $to;
        }

        if (in_array($status, ['diagnosed', 'awaiting_approval', 'awaiting_parts', 'in_repair', 'qc', 'ready_for_pickup', 'released', 'unclaimed'], true)) {
            $service = $services->random();
            $amount = $service->default_price;

            TicketLine::factory()->create([
                'repair_ticket_id' => $ticket->id,
                'line_type' => 'labor',
                'service_id' => $service->id,
                'description' => $service->name,
                'unit_price' => $amount,
                'amount' => $amount,
            ]);

            $ticket->update(['estimated_cost' => $amount, 'approved_amount' => $amount]);
        }

        if ($status === 'released') {
            $ticket->update(['downpayment' => $ticket->approved_amount, 'balance' => 0]);

            Payment::factory()->create([
                'payable_type' => 'repair_ticket',
                'payable_id' => $ticket->id,
                'method' => fake()->randomElement(['cash', 'gcash', 'maya']),
                'amount' => $ticket->approved_amount,
                'actor_id' => $technician?->id,
                'created_at' => $cursor,
            ]);

            Warranty::factory()->create([
                'repair_ticket_id' => $ticket->id,
                'issued_at' => $cursor,
                'expiry_date' => $cursor->clone()->addDays(30),
            ]);

            ImeiVerification::factory()->create([
                'repair_ticket_id' => $ticket->id,
                'phase' => 'release',
                'scanned_imei' => $ticket->customerDevice?->imei_normalized ?? fake()->numerify('###############'),
                'actor_id' => $technician?->id,
                'created_at' => $cursor,
            ]);
        }

        if ($status === 'unclaimed') {
            UnclaimedNotice::factory()->create([
                'repair_ticket_id' => $ticket->id,
                'stage' => 30,
                'generated_at' => $cursor->clone()->addDays(30),
                'delivered_at' => $cursor->clone()->addDays(30),
            ]);
        }
    }

    private function ticketNumber(Branch $branch, Carbon $at): string
    {
        $n = Sequence::next($branch->id, 'ticket', (int) $at->format('Y'), (int) $at->format('m'));

        return sprintf('JO-%s-%s-%04d', $branch->code, $at->format('Ym'), $n);
    }
}
