<?php

namespace App\Http\Requests\Api\V1\Service;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Service::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:60'],
            'default_price' => ['required', 'numeric', 'min:0'],
            'default_duration_minutes' => ['nullable', 'integer', 'min:0'],
            'warranty_days' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
