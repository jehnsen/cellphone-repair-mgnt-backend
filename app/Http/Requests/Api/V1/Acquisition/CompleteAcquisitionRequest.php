<?php

namespace App\Http\Requests\Api\V1\Acquisition;

use App\Models\SerializedUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteAcquisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('acquisition'));
    }

    public function rules(): array
    {
        return [
            'product_ulid' => ['required', 'string', Rule::exists('products', 'ulid')],
            'condition' => ['required', Rule::in(SerializedUnit::CONDITIONS)],
            'warranty_terms' => ['nullable', 'string'],
        ];
    }
}
