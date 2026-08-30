<?php

namespace App\Repositories;

use App\Models\MessageTemplate;
use App\Repositories\Contracts\MessageTemplateRepositoryInterface;

class MessageTemplateRepository extends BaseRepository implements MessageTemplateRepositoryInterface
{
    protected function modelClass(): string
    {
        return MessageTemplate::class;
    }

    protected function allowedFilters(): array
    {
        return ['channel', 'event_key', 'is_active'];
    }

    protected function allowedSorts(): array
    {
        return ['channel', 'event_key', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return 'event_key';
    }
}
