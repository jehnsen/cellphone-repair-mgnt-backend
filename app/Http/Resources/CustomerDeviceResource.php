<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CustomerDevice */
class CustomerDeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'imei' => $this->imei_normalized,
            'serial_number' => $this->serial_number,
            'color' => $this->color,
            'notes' => $this->notes,
            'device_model' => new DeviceModelResource($this->whenLoaded('deviceModel')),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
