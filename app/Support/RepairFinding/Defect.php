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

    /**
     * How the defect reads at the bench.
     *
     * Spelled out rather than derived from the case name: Str::headline()
     * turns power_ic into "Power Ic" and camera_rear into "Camera Rear",
     * which is how nobody in a repair shop says either of them. The wording
     * matches the frontend's own DEFECT_LABEL so the findings form and the
     * diagnosis visualizer never disagree about what a defect is called.
     */
    public function label(): string
    {
        return match ($this) {
            self::Screen => 'Screen',
            self::Digitizer => 'Digitizer / touch',
            self::Battery => 'Battery',
            self::ChargingPort => 'Charging port',
            self::Motherboard => 'Motherboard',
            self::PowerIc => 'Power IC',
            self::CameraRear => 'Rear camera',
            self::CameraFront => 'Front camera',
            self::Speaker => 'Loudspeaker',
            self::Earpiece => 'Earpiece',
            self::Microphone => 'Microphone',
            self::Buttons => 'Buttons',
            self::BackCover => 'Back cover',
            self::Housing => 'Housing / frame',
            self::SimReader => 'SIM reader',
            self::SdReader => 'SD reader',
            self::WifiAntenna => 'Wi-Fi antenna',
            self::Other => 'Other',
        };
    }
}
