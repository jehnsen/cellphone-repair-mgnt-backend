<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicDiagnosisResource;
use App\Http\Resources\PublicVerificationResource;
use App\Models\RepairTicket;
use App\Services\DiagnosisVisualService;
use App\Services\PublicVerificationService;
use App\Support\Diagnosis\IssueSource;
use App\Support\Diagnosis\ProblemTag;
use App\Support\RepairFinding\Defect;
use Illuminate\Support\Str;

class PublicVerificationController extends Controller
{
    public function __construct(
        private readonly PublicVerificationService $verification,
        private readonly DiagnosisVisualService $diagnosis,
    ) {}

    public function show(string $token): PublicVerificationResource
    {
        return new PublicVerificationResource($this->verification->findByToken($token));
    }

    /**
     * The read-only diagnosis view behind the link a technician sends the
     * customer. Unauthenticated like its sibling, and redacted on the same
     * terms — see PublicDiagnosisResource.
     */
    public function diagnosis(string $token): PublicDiagnosisResource
    {
        $ticket = $this->verification->findDiagnosisByToken($token);

        [$source, $issueKeys] = $this->issuesFor($ticket);

        return new PublicDiagnosisResource([
            'ticket_number' => $ticket->ticket_number,
            'status' => $ticket->status,
            'device' => [
                'brand' => $ticket->device_brand_snapshot,
                'model' => $ticket->device_model_snapshot,
                'color' => $ticket->device_color_snapshot,
            ],
            'branch' => ['name' => $ticket->branch?->name],
            'issues' => array_map(
                fn (string $key) => [
                    'source' => $source->value,
                    'key' => $key,
                    'label' => $this->issueLabel($source, $key),
                ],
                $issueKeys,
            ),
            'implicated_part_keys' => $this->diagnosis->partKeysFor($source, $issueKeys),
            'parts' => $this->diagnosis->parts(),
            // Bench working notes and QC state stay inside the shop; the
            // conclusion the customer is told does not.
            'finding' => $ticket->finding === null ? null : [
                'summary' => $ticket->finding->summary,
                'details' => $ticket->finding->details,
                'root_cause' => $ticket->finding->root_cause,
                'resolution' => $ticket->finding->resolution,
            ],
            'diagnosed_at' => $ticket->finding?->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * Which vocabulary describes this ticket right now.
     *
     * A finding means the unit has been on the bench and a technician has said
     * what it actually is, so its defects win. Without one there is still the
     * customer's own account from intake, which is worth showing — it is what
     * they told the counter, and seeing it drawn is how they confirm the shop
     * understood them.
     *
     * @return array{0: IssueSource, 1: list<string>}
     */
    private function issuesFor(RepairTicket $ticket): array
    {
        $defects = $ticket->finding?->defects ?? [];

        if ($defects !== []) {
            return [IssueSource::Defect, array_values($defects)];
        }

        return [IssueSource::ProblemTag, array_values($ticket->problem_tags ?? [])];
    }

    private function issueLabel(IssueSource $source, string $key): string
    {
        return match ($source) {
            IssueSource::ProblemTag => ProblemTag::tryFrom($key)?->label() ?? Str::headline($key),
            IssueSource::Defect => Defect::tryFrom($key)?->label() ?? $key,
        };
    }
}
