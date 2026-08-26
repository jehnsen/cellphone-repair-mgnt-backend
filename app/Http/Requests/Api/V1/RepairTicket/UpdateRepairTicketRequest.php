<?php

namespace App\Http\Requests\Api\V1\RepairTicket;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRepairTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('ticket'));
    }

    public function rules(): array
    {
        return [
            'assigned_technician_ulid' => ['nullable', 'string', Rule::exists('users', 'ulid')],
            'reported_problem' => ['nullable', 'string'],
            'problem_tags' => ['nullable', 'array'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'approved_amount' => ['nullable', 'numeric', 'min:0'],
            'downpayment' => ['nullable', 'numeric', 'min:0'],
            'promised_date' => ['nullable', 'date'],
            'warranty_days_offered' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
