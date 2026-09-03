<?php

namespace App\Http\Requests\Api\V1\SalesWarranty;

use App\Models\SupplierReturn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', SupplierReturn::class);
    }

    public function rules(): array
    {
        return [
            'serialized_unit_ulid' => ['required', 'string', Rule::exists('serialized_units', 'ulid')],
            'supplier_ulid' => ['required', 'string', Rule::exists('suppliers', 'ulid')],
            'sale_warranty_claim_ulid' => ['nullable', 'string', Rule::exists('sale_warranty_claims', 'ulid')],
            'reason' => ['required', Rule::in(SupplierReturn::REASONS)],
            'reason_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
