<?php

namespace App\Http\Requests\Api\V1\RepairTicket;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('ticket'));
    }

    public function rules(): array
    {
        return [
            'line_type' => ['required', Rule::in(['part', 'labor'])],
            'product_ulid' => ['required_if:line_type,part', 'prohibited_if:line_type,labor', 'string', Rule::exists('products', 'ulid')],
            'service_ulid' => ['required_if:line_type,labor', 'prohibited_if:line_type,part', 'string', Rule::exists('services', 'ulid')],
            'description' => ['required', 'string', 'max:160'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
