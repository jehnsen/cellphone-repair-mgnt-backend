<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\RepairTicket
 *
 * Deliberately redacted (docs/design/01-domain-design.md §6, "unauthenticated,
 * strict-limited, redacted"): no customer name/contact, no claim_code (the
 * pickup credential, not a public proof), no unlock info, no pricing, no
 * technician identity — just enough to prove this device genuinely passed
 * through this shop.
 */
class PublicVerificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ticket_number' => $this->ticket_number,
            'status' => $this->status,
            'device' => [
                'brand' => $this->device_brand_snapshot,
                'model' => $this->device_model_snapshot,
                'color' => $this->device_color_snapshot,
            ],
            'branch' => [
                'name' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            ],
            'promised_date' => $this->promised_date?->toDateString(),
            'warranty' => $this->whenLoaded('warranty', fn () => $this->warranty ? [
                'warranty_code' => $this->warranty->warranty_code,
                'days' => $this->warranty->days,
                'expiry_date' => $this->warranty->expiry_date?->toDateString(),
            ] : null),
            'verified_at' => now()->toIso8601String(),
        ];
    }
}
