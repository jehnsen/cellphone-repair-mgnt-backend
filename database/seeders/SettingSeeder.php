<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Shop-wide defaults (branch_id = null). A branch overrides any of these
 * through PUT /api/v1/settings; receipt header/footer, TIN, and VAT
 * registration are real columns on `branches`, not here.
 */
class SettingSeeder extends Seeder
{
    private const GLOBALS = [
        ['bir.display_on_receipt', true, 'bool'],
        ['bir.min_info_notice', 'This serves as your Official Receipt.', 'string'],
        ['pos.round_to_nearest_centavo', true, 'bool'],
        ['notifications.sms_enabled', false, 'bool'],
        ['notifications.viber_enabled', false, 'bool'],
        ['tickets.default_warranty_days', 7, 'int'],
        ['tickets.unclaimed_notice_days', [30, 60, 90], 'json'],
    ];

    public function run(): void
    {
        foreach (self::GLOBALS as [$key, $value, $type]) {
            Setting::query()->updateOrCreate(
                ['branch_id' => null, 'key' => $key],
                ['value' => $value, 'type' => $type],
            );
        }
    }
}
