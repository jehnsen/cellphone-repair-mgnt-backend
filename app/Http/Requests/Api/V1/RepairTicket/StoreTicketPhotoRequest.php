<?php

namespace App\Http\Requests\Api\V1\RepairTicket;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('ticket'));
    }

    public function rules(): array
    {
        return [
            'phase' => ['required', Rule::in(['intake', 'pre_repair', 'post_repair', 'release'])],
            'photo' => ['required', 'image', 'max:8192'],
        ];
    }
}
