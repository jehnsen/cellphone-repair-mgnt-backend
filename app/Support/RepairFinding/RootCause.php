<?php

namespace App\Support\RepairFinding;

/**
 * Why a unit failed — exactly one per repair_findings row. Drives the
 * "how often is it the charging port on this model" style reporting the
 * free-text timeline could never answer. Server-side source of truth;
 * exposed to the frontend via GET /api/v1/meta/enums so it never keeps a
 * second hardcoded copy.
 */
enum RootCause: string
{
    case DropImpact = 'drop_impact';
    case LiquidIngress = 'liquid_ingress';
    case ComponentWear = 'component_wear';
    case PowerSurge = 'power_surge';
    case ThirdPartyRepair = 'third_party_repair';
    case FirmwareCorruption = 'firmware_corruption';
    case ManufacturingDefect = 'manufacturing_defect';
    case UserError = 'user_error';
    case NoFaultFound = 'no_fault_found';
    case Other = 'other';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
