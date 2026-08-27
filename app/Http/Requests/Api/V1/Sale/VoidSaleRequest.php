<?php

namespace App\Http\Requests\Api\V1\Sale;

use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;

class VoidSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('void', Sale::class);
    }

    public function rules(): array
    {
        return [
            'void_reason' => ['required', 'string', 'max:255'],
        ];
    }
}
