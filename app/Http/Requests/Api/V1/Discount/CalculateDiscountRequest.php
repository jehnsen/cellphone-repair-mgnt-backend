<?php

namespace App\Http\Requests\Api\V1\Discount;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalculateDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sales.create');
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'type' => ['required', Rule::in(['percent', 'amount', 'senior_citizen', 'pwd'])],
            'value' => ['required_unless:type,senior_citizen,pwd', 'nullable', 'numeric', 'min:0'],
        ];
    }
}
