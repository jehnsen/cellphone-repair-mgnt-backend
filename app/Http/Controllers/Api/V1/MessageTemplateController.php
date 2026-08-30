<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MessageTemplate\StoreMessageTemplateRequest;
use App\Http\Requests\Api\V1\MessageTemplate\UpdateMessageTemplateRequest;
use App\Http\Resources\MessageTemplateResource;
use App\Models\MessageTemplate;
use App\Services\MessageTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET|POST /api/v1/message-templates, GET|PATCH /api/v1/message-templates/{ulid}
 * (docs/design/01-domain-design.md §6). No destroy — a retired template is
 * `is_active = false`, not a deleted row.
 */
class MessageTemplateController extends Controller
{
    public function __construct(private readonly MessageTemplateService $templates) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless((bool) $request->user()?->can('settings.manage'), 403, 'You do not have permission to manage settings.');

        return MessageTemplateResource::collection($this->templates->list());
    }

    public function store(StoreMessageTemplateRequest $request): JsonResponse
    {
        $template = $this->templates->create($request->validated());

        return (new MessageTemplateResource($template))->response()->setStatusCode(201);
    }

    public function show(Request $request, MessageTemplate $messageTemplate): MessageTemplateResource
    {
        abort_unless((bool) $request->user()?->can('settings.manage'), 403, 'You do not have permission to manage settings.');

        return new MessageTemplateResource($messageTemplate);
    }

    public function update(UpdateMessageTemplateRequest $request, MessageTemplate $messageTemplate): MessageTemplateResource
    {
        $template = $this->templates->update($messageTemplate, $request->validated());

        return new MessageTemplateResource($template);
    }
}
