<?php

namespace App\Http\Requests\Api\V1\Acquisition;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImeiCheckAcquisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('acquisition'));
    }

    public function rules(): array
    {
        return [
            'result' => ['required', Rule::in(['clear', 'flagged'])],
        ];
    }
}
