<?php

namespace App\Services;

use App\Models\RepairFinding;
use App\Models\RepairTicket;
use App\Models\User;
use App\Support\Api\ApiException;
use App\Support\Api\ErrorCode;
use App\Support\RepairFinding\Defect;
use App\Support\RepairFinding\Resolution;
use App\Support\RepairFinding\RootCause;
use Illuminate\Support\Facades\DB;

/**
 * One findings record per ticket (docs spec "Findings & root cause"),
 * updated in place. There are no superseded rows: every write appends a
 * `finding_recorded` timeline event, and that is what preserves the
 * history. Recording a finding never changes ticket status — the board
 * drives transitions, findings are a separate decision.
 */
class RepairFindingService
{
    public function __construct(private readonly TicketEventRecorder $events) {}

    public function find(RepairTicket $ticket): ?RepairFinding
    {
        return $ticket->finding()
            ->with([
                'recordedBy' => fn ($query) => $query->withoutGlobalScopes(),
                'qcCheckedBy' => fn ($query) => $query->withoutGlobalScopes(),
            ])
            ->first();
    }

    public function upsert(RepairTicket $ticket, array $data, User $actor): RepairFinding
    {
        // A released ticket's record is closed — corrections go on the
        // timeline, not into the row (same lock RepairTicketService::update
        // applies to every other pre-release edit).
        if ($ticket->status === 'released') {
            throw new ApiException(
                ErrorCode::InvalidStatusTransition,
                'A released ticket\'s findings record is closed. Record corrections on the timeline.',
            );
        }

        return DB::transaction(function () use ($ticket, $data, $actor) {
            $existing = $ticket->finding()->first();

            $attributes = [
                'summary' => $data['summary'],
                'details' => $data['details'] ?? null,
                'root_cause' => $data['root_cause'],
                'defects' => $data['defects'] ?? null,
                'resolution' => $data['resolution'],
                'technician_notes' => $data['technician_notes'] ?? null,
                'qc_passed' => $data['qc_passed'] ?? null,
            ];

            // qc_checked_at / qc_checked_by stamp the moment qc_passed goes
            // from "not yet checked" (null) to a real true/false verdict.
            $wasChecked = $existing?->qc_passed !== null;
            $nowChecked = array_key_exists('qc_passed', $data) && $data['qc_passed'] !== null;

            if (! $wasChecked && $nowChecked) {
                $attributes['qc_checked_at'] = now();
                $attributes['qc_checked_by_id'] = $actor->id;
            } elseif ($existing !== null) {
                $attributes['qc_checked_at'] = $existing->qc_checked_at;
                $attributes['qc_checked_by_id'] = $existing->qc_checked_by_id;
            }

            if ($existing !== null) {
                $existing->update($attributes);
                $finding = $existing->fresh();
                $verb = 'updated';
            } else {
                $finding = RepairFinding::create([
                    ...$attributes,
                    'repair_ticket_id' => $ticket->id,
                    'recorded_by_id' => $actor->id,
                ]);
                $verb = 'recorded';
            }

            $this->events->record(
                $ticket,
                'finding_recorded',
                $actor,
                note: $this->timelineMessage($verb, $finding),
                metadata: [
                    'root_cause' => $finding->root_cause,
                    'resolution' => $finding->resolution,
                    'defects' => $finding->defects ?? [],
                    'qc_passed' => $finding->qc_passed,
                ],
            );

            return $finding;
        });
    }

    /** "Findings recorded: charging port, sim reader — liquid ingress (part replaced)." */
    private function timelineMessage(string $verb, RepairFinding $finding): string
    {
        $defects = collect($finding->defects ?? [])
            ->map(fn (string $d) => str_replace('_', ' ', $d))
            ->implode(', ');

        $rootCause = str_replace('_', ' ', $finding->root_cause);
        $resolution = str_replace('_', ' ', $finding->resolution);

        $lead = $defects !== '' ? "{$defects} — {$rootCause}" : $rootCause;

        return "Findings {$verb}: {$lead} ({$resolution}).";
    }

    /**
     * The controlled vocabularies, for GET /api/v1/meta/enums — the
     * frontend reads these rather than keeping a second hardcoded copy.
     *
     * @return array<string, list<string>>
     */
    public static function enums(): array
    {
        return [
            'root_cause' => RootCause::values(),
            'defects' => Defect::values(),
            'resolution' => Resolution::values(),
        ];
    }
}
