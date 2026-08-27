<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\Service;
use App\Models\SerializedUnit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SaleLine
 *
 * sellable resolves the polymorphic sellable_type/sellable_id pair — a
 * plain method call on the model (SaleLine::sellable()), not an Eloquent
 * relation, so this doesn't benefit from eager loading; acceptable N+1
 * for a sale's line count (typically a handful of items).
 */
class SaleLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sellable = $this->sellable();

        return [
            'sellable_type' => $this->sellable_type,
            'sellable' => match (true) {
                $sellable instanceof Product => new ProductResource($sellable),
                $sellable instanceof SerializedUnit => new SerializedUnitResource($sellable),
                $sellable instanceof Service => new ServiceResource($sellable),
                default => null,
            },
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'unit_cost' => $this->when($request->user()?->can('reports.margin.view'), $this->unit_cost),
            'line_discount' => $this->line_discount,
            'amount' => $this->amount,
        ];
    }
}
