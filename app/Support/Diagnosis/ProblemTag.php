<?php

namespace App\Support\Diagnosis;

/**
 * The intake quick-pick chips — the customer's own account of the fault,
 * before anything is opened up.
 *
 * Extracted from StoreRepairTicketRequest, which held the only copy as an
 * inline Rule::in list. The visualizer needs to map these to parts, so the
 * list now has one home instead of being written out a second time.
 */
enum ProblemTag: string
{
    case Screen = 'screen';
    case Battery = 'battery';
    case ChargingPort = 'charging_port';
    case WaterDamage = 'water_damage';
    case NoPower = 'no_power';
    case Software = 'software';
    case Camera = 'camera';
    case Speaker = 'speaker';
    case BoardLevel = 'board_level';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }

    /** How the chip reads at the counter. */
    public function label(): string
    {
        return match ($this) {
            self::Screen => 'Screen',
            self::Battery => 'Battery',
            self::ChargingPort => 'Charging port',
            self::WaterDamage => 'Water damage',
            self::NoPower => 'No power',
            self::Software => 'Software',
            self::Camera => 'Camera',
            self::Speaker => 'Speaker',
            self::BoardLevel => 'Board-level',
        };
    }
}
