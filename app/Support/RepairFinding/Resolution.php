<?php

namespace App\Support\RepairFinding;

/** What was actually done about the fault — exactly one per row. */
enum Resolution: string
{
    case Repaired = 'repaired';
    case PartReplaced = 'part_replaced';
    case Cleaned = 'cleaned';
    case SoftwareRestored = 'software_restored';
    case NoFaultFound = 'no_fault_found';
    case Unrepairable = 'unrepairable';
    case CustomerDeclined = 'customer_declined';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}
