<?php

namespace App\Http\Resources;

use App\Models\MessageTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MessageTemplate */
class MessageTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'channel' => $this->channel,
            'event_key' => $this->event_key,
            'body' => $this->body,
            'merge_fields' => $this->mergeFields(),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** The distinct {{placeholder}} tokens present in the body, for the editor UI. */
    private function mergeFields(): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_.]+)\s*\}\}/i', (string) $this->body, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }
}
