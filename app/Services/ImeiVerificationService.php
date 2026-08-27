<?php

namespace App\Services;

use App\Models\ImeiVerification;
use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Chain of custody (docs/design/01-domain-design.md §2.5): every IMEI scan
 * at intake/pre_repair/post_repair/release is recorded, match or not — a
 * mismatch doesn't reject the request, it's simply logged as
 * matches_expected=false for the human process (and the release guard, see
 * RepairTicketService) to act on. `override()` is the owner-only escape
 * hatch: a separate, self-contained audit row rather than an edit to a
 * past one, since imei_verifications deliberately has no ulid to target.
 */
class ImeiVerificationService
{
    public function __construct(private readonly TicketEventRecorder $events) {}

    public function list(RepairTicket $ticket): Collection
    {
        return $ticket->imeiVerifications()
            ->with([
                'actor' => fn ($query) => $query->withoutGlobalScopes(),
                'overriddenBy' => fn ($query) => $query->withoutGlobalScopes(),
            ])
            ->latest()
            ->get();
    }

    public function verify(RepairTicket $ticket, array $data, User $actor): ImeiVerification
    {
        return DB::transaction(function () use ($ticket, $data, $actor) {
            $expected = $ticket->customerDevice?->imei_normalized;
            $matches = $expected !== null && $expected === $data['scanned_imei'];

            $verification = ImeiVerification::create([
                'repair_ticket_id' => $ticket->id,
                'phase' => $data['phase'],
                'scanned_imei' => $data['scanned_imei'],
                'matches_expected' => $matches,
                'actor_id' => $actor->id,
            ]);

            $this->events->record(
                $ticket,
                'imei_verified',
                $actor,
                note: $matches ? "IMEI verified at {$data['phase']}." : "IMEI MISMATCH at {$data['phase']}.",
                metadata: ['phase' => $data['phase'], 'matches_expected' => $matches],
            );

            return $verification;
        });
    }

    public function override(RepairTicket $ticket, array $data, User $actor): ImeiVerification
    {
        return DB::transaction(function () use ($ticket, $data, $actor) {
            $expected = $ticket->customerDevice?->imei_normalized;
            $matches = $expected !== null && $expected === $data['scanned_imei'];

            $verification = ImeiVerification::create([
                'repair_ticket_id' => $ticket->id,
                'phase' => $data['phase'],
                'scanned_imei' => $data['scanned_imei'],
                'matches_expected' => $matches,
                'actor_id' => $actor->id,
                'override_reason' => $data['override_reason'],
                'overridden_by' => $actor->id,
            ]);

            $this->events->record(
                $ticket,
                'imei_override',
                $actor,
                note: $data['override_reason'],
                metadata: ['phase' => $data['phase'], 'matches_expected' => $matches],
            );

            return $verification;
        });
    }
}
