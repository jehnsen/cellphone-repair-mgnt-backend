<?php

namespace App\Http\Requests\Api\V1\Shift;

use App\Models\Shift;
use Illuminate\Foundation\Http\FormRequest;

class OpenShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('open', Shift::class);
    }

    public function rules(): array
    {
        return [
            'opening_float' => ['required', 'numeric', 'min:0'],
        ];
    }
}
