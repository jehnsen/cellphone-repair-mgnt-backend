<?php

namespace App\Http\Resources;

use App\Models\SaleWarrantyClaim;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SaleWarrantyClaim */
class SaleWarrantyClaimResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'reported_defect' => $this->reported_defect,
            'handling' => $this->handling,
            'within_coverage' => $this->within_coverage,
            'status' => $this->status,
            'resolution' => $this->resolution,
            'outcome_notes' => $this->outcome_notes,
            'repair_ticket_ulid' => $this->whenLoaded('repairTicket', fn () => $this->repairTicket?->ulid),
            'warranty' => new SaleWarrantyResource($this->whenLoaded('warranty')),
            'serialized_unit' => new SerializedUnitResource($this->whenLoaded('serializedUnit')),
            'supplier_return_ulid' => $this->whenLoaded('supplierReturn', fn () => $this->supplierReturn?->ulid),
            'filed_by' => new UserResource($this->whenLoaded('filedBy')),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
