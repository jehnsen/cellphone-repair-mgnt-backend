<?php

namespace App\Http\Requests\Api\V1\Sale;

use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Sale::class);
    }

    public function rules(): array
    {
        return [
            'customer_ulid' => ['nullable', 'string', Rule::exists('customers', 'ulid')],
            'client_uuid' => ['nullable', 'uuid', Rule::unique('sales', 'client_uuid')],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sellable_type' => ['required', Rule::in(['product', 'serialized_unit', 'service'])],
            'lines.*.product_ulid' => ['required_if:lines.*.sellable_type,product', 'prohibited_unless:lines.*.sellable_type,product', 'string', Rule::exists('products', 'ulid')],
            'lines.*.serialized_unit_ulid' => ['required_if:lines.*.sellable_type,serialized_unit', 'prohibited_unless:lines.*.sellable_type,serialized_unit', 'string', Rule::exists('serialized_units', 'ulid')],
            'lines.*.service_ulid' => ['required_if:lines.*.sellable_type,service', 'prohibited_unless:lines.*.sellable_type,service', 'string', Rule::exists('services', 'ulid')],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
            // Serialized-unit lines only: override the shop warranty issued
            // at checkout. Omitted, it falls back to the product's
            // catalog `warranty_days` (0 = issue nothing).
            'lines.*.warranty_days' => ['nullable', 'integer', 'min:0', 'max:3650', 'prohibited_unless:lines.*.sellable_type,serialized_unit'],
            'lines.*.warranty_coverage' => ['nullable', Rule::in(['shop', 'manufacturer']), 'prohibited_unless:lines.*.sellable_type,serialized_unit'],
            'lines.*.warranty_terms' => ['nullable', 'string', 'max:2000', 'prohibited_unless:lines.*.sellable_type,serialized_unit'],
            'lines.*.discount' => ['nullable', 'array'],
            'lines.*.discount.type' => ['required_with:lines.*.discount', Rule::in(['percent', 'amount'])],
            'lines.*.discount.value' => ['required_with:lines.*.discount', 'numeric', 'min:0'],

            'sale_discount' => ['nullable', 'array'],
            'sale_discount.type' => ['required_with:sale_discount', Rule::in(['percent', 'amount', 'senior_citizen', 'pwd'])],
            // senior_citizen/pwd default to the legally-fixed 20% in
            // SaleService when omitted — see withValidator() below for why
            // this can't just be a required_unless rule (it can't tell
            // "sale_discount absent" from "sale_discount.type absent").
            'sale_discount.value' => ['nullable', 'numeric', 'min:0'],
            'sale_discount.id_type' => ['required_if:sale_discount.type,senior_citizen,pwd', 'nullable', 'string', 'max:30'],
            'sale_discount.id_number' => ['required_if:sale_discount.type,senior_citizen,pwd', 'nullable', 'string', 'max:40'],
            'sale_discount.cardholder_name' => ['required_if:sale_discount.type,senior_citizen,pwd', 'nullable', 'string', 'max:120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $sd = $this->input('sale_discount');
            if (is_array($sd) && in_array($sd['type'] ?? null, ['percent', 'amount'], true) && ! isset($sd['value'])) {
                $validator->errors()->add('sale_discount.value', 'A percent/amount discount needs a value.');
            }
        });
    }
}
