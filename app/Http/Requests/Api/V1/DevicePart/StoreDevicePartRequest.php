<?php

namespace App\Http\Requests\Api\V1\DevicePart;

use App\Models\DevicePart;
use App\Support\Diagnosis\PartCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDevicePartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', DevicePart::class);
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:48', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('device_parts', 'key')],
            'label' => ['required', 'string', 'max:80'],
            'category' => ['required', Rule::in(PartCategory::values())],
            'blurb' => ['nullable', 'string', 'max:2000'],

            // Geometry is millimetres against a nominal 150 x 72 x 8 mm slab.
            // The bounds are generous rather than exact — a part outside them
            // is a typo, not a bigger phone.
            'position' => ['required', 'array'],
            'position.x' => ['required', 'numeric', 'between:-200,200'],
            'position.y' => ['required', 'numeric', 'between:-200,200'],
            'position.z' => ['required', 'numeric', 'between:-200,200'],

            'size' => ['required', 'array'],
            'size.x' => ['required', 'numeric', 'gt:0', 'max:400'],
            'size.y' => ['required', 'numeric', 'gt:0', 'max:400'],
            'size.z' => ['required', 'numeric', 'gt:0', 'max:400'],

            'explode' => ['nullable', 'array'],
            'explode.x' => ['nullable', 'numeric', 'between:-10,10'],
            'explode.y' => ['nullable', 'numeric', 'between:-10,10'],
            'explode.z' => ['nullable', 'numeric', 'between:-10,10'],
            'explode.distance' => ['nullable', 'numeric', 'min:0', 'max:500'],

            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['boolean'],
        ];
    }
}
