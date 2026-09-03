<?php

namespace App\Http\Requests\Api\V1\SalesWarranty;

use App\Models\SaleWarrantyClaim;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleWarrantyClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', SaleWarrantyClaim::class);
    }

    public function rules(): array
    {
        return [
            'reported_defect' => ['required', 'string', 'max:2000'],
            'handling' => ['nullable', Rule::in(SaleWarrantyClaim::HANDLINGS)],
            // Optional even when handling is repair_board — the bench ticket
            // can be attached now or not at all.
            'repair_ticket_ulid' => [
                'nullable',
                'prohibited_unless:handling,repair_board',
                'string',
                Rule::exists('repair_tickets', 'ulid'),
            ],
        ];
    }
}
