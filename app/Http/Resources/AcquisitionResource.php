<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Acquisition */
class AcquisitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'seller_name' => $this->seller_name,
            'seller_id_type' => $this->seller_id_type,
            'seller_id_number' => $this->seller_id_number,
            'declared_source' => $this->declared_source,
            'offered_price' => $this->offered_price,
            'imei' => $this->imei,
            'condition_assessment' => $this->condition_assessment,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'imei_check_result' => $this->imei_check_result,
            'imei_checked_at' => $this->imei_checked_at?->toIso8601String(),
            'resulting_serialized_unit' => new SerializedUnitResource($this->whenLoaded('resultingSerializedUnit')),
            'processor' => new UserResource($this->whenLoaded('processor')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
