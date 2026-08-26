<?php

namespace App\Http\Requests\Api\V1\DeviceModel;

use App\Models\DeviceModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeviceModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', DeviceModel::class);
    }

    public function rules(): array
    {
        return [
            'device_brand_ulid' => ['required', 'string', Rule::exists('device_brands', 'ulid')],
            'name' => ['required', 'string', 'max:255'],
            'release_year' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'aliases' => ['nullable', 'array'],
            'aliases.*' => ['string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
