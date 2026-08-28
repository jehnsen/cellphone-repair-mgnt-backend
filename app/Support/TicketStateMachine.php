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
 * The transition graph itself doesn't encode the following — it lives as a
 * guard in RepairTicketService::transition() instead, since it depends on
 * state outside the status column:
 *   - ready_for_pickup/unclaimed → released requires a settled balance
 *     (Stage 8 / POS payments) — see assertBalanceSettledForRelease().
 * ready_for_pickup/unclaimed → released deliberately does NOT require a
 * release-phase IMEI verification (or override) — that guard existed
 * briefly but was removed: it blocked real releases whenever staff hadn't
 * run a release-phase scan, and IMEI verification is chain-of-custody
 * documentation, not a release gate. imei-verifications/override still
 * record every scan and every override exactly as before.
 * Also still open per docs/design §7 Flag 9: what happens after an
 * unclaimed unit ages out — no "forfeited" state exists yet.
 */
class TicketStateMachine
{
    private const TRANSITIONS = [
        'received' => ['diagnosed', 'unrepairable'],
        'diagnosed' => ['awaiting_approval', 'in_repair', 'unrepairable'],
        'awaiting_approval' => ['in_repair', 'awaiting_parts', 'returned_as_is'],
        'awaiting_parts' => ['in_repair', 'unrepairable'],
        // ready_for_pickup direct from in_repair matches the board's actual
        // columns (TO CHECK / WAITING FOR CUSTOMER / WAITING FOR PARTS /
        // IN REPAIR / READY TO CLAIM — no separate QC column). QC now lives
        // as data on the ticket's finding record (finding.qc_passed), not
        // as a required intermediate ticket status. `qc` itself stays a
        // legal status/edge for any ticket already sitting there or for a
        // branch that still wants the extra checkpoint.
        'in_repair' => ['ready_for_pickup', 'qc', 'awaiting_parts', 'unrepairable'],
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
