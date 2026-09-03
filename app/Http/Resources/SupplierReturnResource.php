<?php

namespace App\Http\Resources;

use App\Models\SupplierReturn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SupplierReturn */
class SupplierReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'reason' => $this->reason,
            'reason_note' => $this->reason_note,
            'status' => $this->status,
            'credit_amount' => $this->when($request->user()?->can('reports.margin.view'), $this->credit_amount),
            'sent_at' => $this->sent_at?->toDateString(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'sale_warranty_claim_ulid' => $this->whenLoaded('claim', fn () => $this->claim?->ulid),
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'serialized_unit' => new SerializedUnitResource($this->whenLoaded('serializedUnit')),
            'replacement_serialized_unit' => new SerializedUnitResource($this->whenLoaded('replacementSerializedUnit')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
