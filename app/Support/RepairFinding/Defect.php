<?php

namespace App\Support\RepairFinding;

/**
 * Component-level defects found during the repair — zero or more per row,
 * stored as a JSON array. Deliberately mirrors the intake ConditionCheck
 * vocabulary where they overlap, so intake condition and post-repair
 * findings can be compared for the same unit.
 */
enum Defect: string
{
    case Screen = 'screen';
    case Digitizer = 'digitizer';
    case Battery = 'battery';
    case ChargingPort = 'charging_port';
    case Motherboard = 'motherboard';
    case PowerIc = 'power_ic';
    case CameraRear = 'camera_rear';
    case CameraFront = 'camera_front';
    case Speaker = 'speaker';
    case Earpiece = 'earpiece';
    case Microphone = 'microphone';
    case Buttons = 'buttons';
    case BackCover = 'back_cover';
    case Housing = 'housing';
    case SimReader = 'sim_reader';
    case SdReader = 'sd_reader';
    case WifiAntenna = 'wifi_antenna';
    case Other = 'other';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $d) => $d->value, self::cases());
    }
}
