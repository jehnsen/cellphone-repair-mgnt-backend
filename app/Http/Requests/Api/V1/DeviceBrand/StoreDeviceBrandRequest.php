<?php

namespace App\Http\Requests\Api\V1\DeviceBrand;

use App\Models\DeviceBrand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeviceBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', DeviceBrand::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('device_brands', 'name')],
            'logo_ref' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
