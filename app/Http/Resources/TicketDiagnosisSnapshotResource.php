<?php

namespace App\Http\Resources;

use App\Models\TicketDiagnosisSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A frozen diagnosis view. The image itself is reached through a short-TTL
 * signed URL set on the model by the controller — binary never comes back
 * through the API (Rule Zero), same as a ticket photo.
 *
 * @mixin TicketDiagnosisSnapshot
 */
class TicketDiagnosisSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'issue_source' => $this->issue_source,
            'issue_keys' => $this->issue_keys ?? [],
            'part_keys' => $this->part_keys ?? [],
            'camera' => $this->camera,
            'note' => $this->note,
            'sha256_hash' => $this->sha256_hash,
            'signed_url' => $this->signed_url ?? null,
            'captured_by' => new UserResource($this->whenLoaded('capturedBy')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
