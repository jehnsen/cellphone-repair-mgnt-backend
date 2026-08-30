<?php

namespace App\Services;

use App\Models\MessageTemplate;
use App\Repositories\Contracts\MessageTemplateRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MessageTemplateService
{
    public function __construct(private readonly MessageTemplateRepositoryInterface $templates) {}

    public function list(): LengthAwarePaginator
    {
        return $this->templates->paginate();
    }

    public function create(array $data): MessageTemplate
    {
        return $this->templates->create($data);
    }

    public function update(MessageTemplate $template, array $data): MessageTemplate
    {
        return $this->templates->update($template, $data);
    }
}
