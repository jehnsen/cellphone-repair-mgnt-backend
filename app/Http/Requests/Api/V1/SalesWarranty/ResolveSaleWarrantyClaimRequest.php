<?php

namespace App\Http\Requests\Api\V1\SalesWarranty;

use App\Models\SaleWarrantyClaim;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveSaleWarrantyClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('resolve', $this->route('saleWarrantyClaim'));
    }

    public function rules(): array
    {
        return [
            'resolution' => ['required', Rule::in(SaleWarrantyClaim::RESOLUTIONS)],
            'outcome_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
