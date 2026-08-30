<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Database\Factories\MessageTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Viber/SMS/email copy with `{{merge_field}}` placeholders, keyed by
 * (channel, event_key). Config, not a record with history — deactivated
 * via `is_active`, never soft-deleted (docs/design/01-domain-design.md
 * §2.10 / Flag 1). Shop-wide, not branch-scoped.
 */
#[Fillable(['channel', 'event_key', 'body', 'is_active'])]
class MessageTemplate extends Model
{
    /** @use HasFactory<MessageTemplateFactory> */
    use HasFactory, HasUlid;

    public const CHANNELS = ['viber', 'sms', 'email'];

    /**
     * The lifecycle hooks a template can be attached to. Kept in sync with
     * MessageTemplateFactory and the (as-yet-unbuilt) notification
     * dispatcher; the API validates against this list.
     */
    public const EVENT_KEYS = [
        'ticket.received',
        'ticket.ready_for_pickup',
        'ticket.released',
        'ticket.unclaimed_30',
        'ticket.unclaimed_60',
        'ticket.unclaimed_90',
        'quote.sent',
        'warranty.expiring_soon',
        'installment.due_reminder',
        'installment.overdue',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
