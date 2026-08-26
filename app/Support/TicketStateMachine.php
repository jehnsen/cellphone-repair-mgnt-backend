<?php

namespace App\Support;

use App\Support\Api\ApiException;
use App\Support\Api\ErrorCode;

/**
 * The legal transition graph from docs/design/01-domain-design.md §4 — the
 * brief fixes the 11-status set but not the graph between them, so this is
 * the concrete design decision. Terminal states: released, returned_as_is.
 * unclaimed is semi-terminal — it only exits via released.
 *
 * Guards not yet enforced here (later-stage dependencies): the
 * ready_for_pickup → released edge should additionally require a matching
 * IMEI verification (Stage 6 / chain of custody) and a settled balance
 * (Stage 8 / POS payments). Both are still open per docs/design §7 Flag 9
 * for what happens after an unclaimed unit ages out — no "forfeited" state
 * exists yet.
 */
class TicketStateMachine
{
    private const TRANSITIONS = [
        'received' => ['diagnosed', 'unrepairable'],
        'diagnosed' => ['awaiting_approval', 'in_repair', 'unrepairable'],
        'awaiting_approval' => ['in_repair', 'awaiting_parts', 'returned_as_is'],
        'awaiting_parts' => ['in_repair', 'unrepairable'],
        'in_repair' => ['qc', 'awaiting_parts', 'unrepairable'],
        'qc' => ['ready_for_pickup', 'in_repair'],
        'ready_for_pickup' => ['released', 'unclaimed'],
        'unclaimed' => ['released'],
        'unrepairable' => ['returned_as_is'],
        'returned_as_is' => [],
        'released' => [],
    ];

    /** @return string[] */
    public static function allowedFrom(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::allowedFrom($from), true);
    }

    /** @throws ApiException with ErrorCode::InvalidStatusTransition and the allowed set in details */
    public static function assertCanTransition(string $from, string $to): void
    {
        if (self::canTransition($from, $to)) {
            return;
        }

        throw new ApiException(
            ErrorCode::InvalidStatusTransition,
            "Cannot transition a ticket from '{$from}' to '{$to}'.",
            [['from' => $from, 'requested' => $to, 'allowed' => self::allowedFrom($from)]],
        );
    }
}
