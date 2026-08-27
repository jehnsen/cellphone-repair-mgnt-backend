<?php

namespace App\Http\Resources;

use App\Models\RepairFinding;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RepairFinding */
class RepairFindingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'summary' => $this->summary,
            'details' => $this->details,
            'root_cause' => $this->root_cause,
            'defects' => $this->defects ?? [],
            'resolution' => $this->resolution,
            'technician_notes' => $this->technician_notes,
            'qc_passed' => $this->qc_passed,
            'qc_checked_at' => $this->qc_checked_at?->toIso8601String(),
            'qc_checked_by' => new UserResource($this->whenLoaded('qcCheckedBy')),
            'recorded_by' => new UserResource($this->whenLoaded('recordedBy')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
