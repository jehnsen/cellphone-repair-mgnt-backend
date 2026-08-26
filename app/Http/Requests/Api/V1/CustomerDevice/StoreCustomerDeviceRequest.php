<?php

namespace App\Http\Requests\Api\V1\CustomerDevice;

use App\Models\CustomerDevice;
use App\Rules\ValidImei;
use App\Support\Imei;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CustomerDevice::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('imei')) {
            $this->merge(['imei' => Imei::normalize($this->string('imei'))]);
        }
    }

    public function rules(): array
    {
        return [
            'device_model_ulid' => ['nullable', 'string', Rule::exists('device_models', 'ulid')],
            'imei' => ['nullable', new ValidImei],
            'serial_number' => ['nullable', 'string', 'max:40'],
            'color' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
