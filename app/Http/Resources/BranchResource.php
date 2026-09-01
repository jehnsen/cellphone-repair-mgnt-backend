<?php

namespace App\Http\Resources;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Branch */
class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type->value,
            // Saves the client hardcoding a second copy of the rule — the
            // frontend hides the repair tabs on a sales-only branch.
            'offers_repairs' => $this->offersRepairs(),
            'legal_name' => $this->legal_name,
            'address' => [
                'line1' => $this->address_line1,
                'line2' => $this->address_line2,
                'city' => $this->city,
                'province' => $this->province,
                'postal_code' => $this->postal_code,
            ],
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,
            'tin' => $this->tin,
            'bir_permit_no' => $this->bir_permit_no,
            'vat_registered' => $this->vat_registered,
            'receipt_header_text' => $this->receipt_header_text,
            'receipt_footer_text' => $this->receipt_footer_text,
            'timezone' => $this->timezone,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
