<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\GoodsReceipt */
class GoodsReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'status' => $this->status,
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'receiver' => new UserResource($this->whenLoaded('receiver')),
            'lines' => GoodsReceiptLineResource::collection($this->whenLoaded('lines')),
            'received_at' => $this->received_at?->toIso8601String(),
        ];
    }
}
