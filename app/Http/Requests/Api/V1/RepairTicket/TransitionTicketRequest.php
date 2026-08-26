<?php

namespace App\Http\Requests\Api\V1\RepairTicket;

use App\Models\RepairTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('transitionTo', [$this->route('ticket'), $this->string('to_status')->toString()]);
    }

    public function rules(): array
    {
        return [
            'to_status' => ['required', Rule::in(RepairTicket::STATUSES)],
            'note' => ['nullable', 'string'],
        ];
    }
}
