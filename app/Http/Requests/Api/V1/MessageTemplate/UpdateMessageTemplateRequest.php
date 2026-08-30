<?php

namespace App\Http\Requests\Api\V1\MessageTemplate;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Only the body and the active flag are editable — the (channel, event_key)
 * identity is fixed once created; a different hook is a different template.
 */
class UpdateMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('settings.manage');
    }

    public function rules(): array
    {
        return [
            'body' => ['sometimes', 'required', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
