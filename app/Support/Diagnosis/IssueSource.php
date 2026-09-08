<?php

namespace App\Support\Diagnosis;

/**
 * Which controlled vocabulary an issue key belongs to.
 *
 * The shop already had two, and the visualizer maps parts for both rather
 * than inventing a third:
 *
 * - `problem_tag` — what the customer walked in saying, chosen at intake
 *   (App\Support\Diagnosis\ProblemTag, nine values).
 * - `defect` — what the technician actually found, recorded on the ticket's
 *   repair finding (App\Support\RepairFinding\Defect, eighteen values).
 *
 * Both are string enums, and neither is unique against the other ('screen'
 * and 'battery' exist in both), so a mapping row is only meaningful with
 * the source alongside the key.
 */
enum IssueSource: string
{
    case ProblemTag = 'problem_tag';
    case Defect = 'defect';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
