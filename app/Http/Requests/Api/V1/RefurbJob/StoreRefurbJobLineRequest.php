<?php

namespace App\Http\Requests\Api\V1\RefurbJob;

use App\Models\RefurbJob;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRefurbJobLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', RefurbJob::class);
    }

    public function rules(): array
    {
        return [
            'product_ulid' => ['required', 'string', Rule::exists('products', 'ulid')],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
        ];
    }
}
