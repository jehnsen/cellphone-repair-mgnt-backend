<?php

namespace App\Http\Requests\Api\V1\RefurbJob;

use App\Models\RefurbJob;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRefurbJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', RefurbJob::class);
    }

    public function rules(): array
    {
        return [
            'acquisition_ulid' => ['required', 'string', Rule::exists('acquisitions', 'ulid')],
            'labor_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
