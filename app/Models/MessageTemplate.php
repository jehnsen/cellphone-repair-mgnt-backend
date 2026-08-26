<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Database\Factories\MessageTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['channel', 'event_key', 'body', 'is_active'])]
class MessageTemplate extends Model
{
    /** @use HasFactory<MessageTemplateFactory> */
    use HasFactory, HasUlid;

    public const CHANNELS = ['viber', 'sms', 'email'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
