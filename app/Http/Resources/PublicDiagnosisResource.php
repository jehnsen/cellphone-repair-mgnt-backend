<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The customer's own view of what is wrong with their unit — the thing a
 * technician texts a link to before asking them to approve a quote.
 *
 * Redacted on the same terms as PublicVerificationResource
 * (docs/design/01-domain-design.md §6): no customer PII, no claim_code, no
 * unlock info, no pricing, no technician identity. Two additions specific to
 * this view, both deliberate:
 *
 * - `finding.summary` and `finding.details` are included. They already reach
 *   the customer on the warranty slip, and a diagnosis link that will not say
 *   what is wrong is not worth sending.
 * - `finding.technician_notes` and the QC fields are *not*. Those are bench
 *   working notes, written on the assumption nobody outside the shop reads
 *   them.
 *
 * The array here is assembled by the controller rather than mixed into a
 * model, because it spans the ticket, its finding, and the parts catalogue.
 */
class PublicDiagnosisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ticket_number' => $this->resource['ticket_number'],
            'status' => $this->resource['status'],
            'device' => $this->resource['device'],
            'branch' => $this->resource['branch'],
            'issues' => $this->resource['issues'],
            'implicated_part_keys' => $this->resource['implicated_part_keys'],
            'parts' => DevicePartResource::collection($this->resource['parts']),
            'finding' => $this->resource['finding'],
            'diagnosed_at' => $this->resource['diagnosed_at'],
        ];
    }
}
