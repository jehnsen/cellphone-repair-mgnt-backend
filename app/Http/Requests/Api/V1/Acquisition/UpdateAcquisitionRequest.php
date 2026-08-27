<?php

namespace App\Http\Requests\Api\V1\Acquisition;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAcquisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('acquisition'));
    }

    public function rules(): array
    {
        return [
            'seller_id_photo_ref' => ['nullable', 'string', 'max:255'],
            'declared_source' => ['nullable', 'string'],
            'offered_price' => ['sometimes', 'numeric', 'min:0'],
            'condition_assessment' => ['nullable', 'string'],
        ];
    }
}
