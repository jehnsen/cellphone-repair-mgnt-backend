<?php

namespace App\Http\Requests\Api\V1\DeviceBrand;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeviceBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('device_brand'));
    }

    public function rules(): array
    {
        $brand = $this->route('device_brand');

        return [
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('device_brands', 'name')->ignore($brand->id)],
            'logo_ref' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
