<?php

namespace App\Http\Requests\Api\V1\MessageTemplate;

use App\Models\MessageTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('settings.manage');
    }

    protected function prepareForValidation(): void
    {
        // A new template is live unless the caller says otherwise — the DB
        // default doesn't reach the response model on a create().
        if (! $this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::in(MessageTemplate::CHANNELS)],
            'event_key' => [
                'required',
                Rule::in(MessageTemplate::EVENT_KEYS),
                // (channel, event_key) is unique — one template per hook per channel.
                Rule::unique('message_templates')->where(
                    fn ($query) => $query->where('channel', $this->input('channel')),
                ),
            ],
            'body' => ['required', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }
}
