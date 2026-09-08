<?php

namespace App\Support\Diagnosis;

/**
 * How the parts of the generic phone rig group in the visualizer's filter.
 * Presentation grouping, not a repair concept — a part belongs to exactly
 * one, and the set is deliberately small enough to fit a tablet's filter row.
 */
enum PartCategory: string
{
    case Display = 'display';
    case Power = 'power';
    case Camera = 'camera';
    case Audio = 'audio';
    case Connectivity = 'connectivity';
    case Structural = 'structural';
    case Board = 'board';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
