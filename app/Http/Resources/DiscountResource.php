<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Discount */
class DiscountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type,
            'value' => $this->value,
            'scope' => $this->scope,
            'id_type' => $this->id_type,
            'id_number' => $this->id_number,
            'cardholder_name' => $this->cardholder_name,
        ];
    }
}
