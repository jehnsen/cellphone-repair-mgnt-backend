<?php

namespace App\Http\Requests\Api\V1\DeviceModel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeviceModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('device_model'));
    }

    public function rules(): array
    {
        return [
            'device_brand_ulid' => ['sometimes', 'string', Rule::exists('device_brands', 'ulid')],
            'name' => ['sometimes', 'string', 'max:255'],
            'release_year' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'aliases' => ['nullable', 'array'],
            'aliases.*' => ['string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
