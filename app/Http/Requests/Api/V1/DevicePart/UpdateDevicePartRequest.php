<?php

namespace App\Http\Requests\Api\V1\DevicePart;

use App\Support\Diagnosis\PartCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDevicePartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('device_part'));
    }

    public function rules(): array
    {
        $part = $this->route('device_part');

        return [
            // The key is what stored snapshots and the issue mapping reference,
            // so it is editable but still unique — renaming one is a migration
            // of meaning, not a typo fix, and the caller has to mean it.
            'key' => [
                'sometimes', 'string', 'max:48', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('device_parts', 'key')->ignore($part->id),
            ],
            'label' => ['sometimes', 'string', 'max:80'],
            'category' => ['sometimes', Rule::in(PartCategory::values())],
            'blurb' => ['nullable', 'string', 'max:2000'],

            'position' => ['sometimes', 'array'],
            'position.x' => ['required_with:position', 'numeric', 'between:-200,200'],
            'position.y' => ['required_with:position', 'numeric', 'between:-200,200'],
            'position.z' => ['required_with:position', 'numeric', 'between:-200,200'],

            'size' => ['sometimes', 'array'],
            'size.x' => ['required_with:size', 'numeric', 'gt:0', 'max:400'],
            'size.y' => ['required_with:size', 'numeric', 'gt:0', 'max:400'],
            'size.z' => ['required_with:size', 'numeric', 'gt:0', 'max:400'],

            'explode' => ['sometimes', 'array'],
            'explode.x' => ['nullable', 'numeric', 'between:-10,10'],
            'explode.y' => ['nullable', 'numeric', 'between:-10,10'],
            'explode.z' => ['nullable', 'numeric', 'between:-10,10'],
            'explode.distance' => ['nullable', 'numeric', 'min:0', 'max:500'],

            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['boolean'],
        ];
    }
}
