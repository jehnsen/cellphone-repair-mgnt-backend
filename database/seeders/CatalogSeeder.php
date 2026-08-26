<?php

namespace Database\Seeders;

use App\Models\DeviceBrand;
use App\Models\DeviceModel;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Real, recognizable PH-market phones and parts rather than fuzzed factory
 * data — 25 device models across the brands actually sold here, 60 products
 * across all three types (see docs/design/01-domain-design.md Testing §).
 */
class CatalogSeeder extends Seeder
{
    private const BRANDS = [
        'Samsung' => [
            ['Galaxy A05', 2024], ['Galaxy A15', 2024], ['Galaxy A55', 2024],
            ['Galaxy S23', 2023], ['Galaxy S24 Ultra', 2024],
        ],
        'Apple' => [
            ['iPhone 11', 2019], ['iPhone 12', 2020], ['iPhone 13', 2021],
            ['iPhone 14', 2022], ['iPhone 15', 2023],
        ],
        'Xiaomi' => [
            ['Redmi 12C', 2023], ['Redmi Note 12', 2023],
            ['Redmi Note 13 Pro', 2024], ['POCO X5', 2023],
        ],
        'Oppo' => [
            ['A18', 2023], ['A58', 2023], ['Reno 10', 2023],
        ],
        'Vivo' => [
            ['Y17s', 2024], ['Y36', 2023], ['V29', 2023],
        ],
        'Realme' => [
            ['C55', 2023], ['Note 50', 2024], ['11 Pro', 2023],
        ],
        'Infinix' => [
            ['Hot 40', 2024], ['Smart 8', 2023],
        ],
    ];

    private const ACCESSORY_NAMES = [
        'Fast Charger 33W', 'USB-C Cable 1m', 'Lightning Cable 1m', 'Wireless Earphones',
        'Wired Earphones', 'Silicone Case', 'Clear Case', 'Tempered Glass Screen Protector',
        'Power Bank 10000mAh', 'Power Bank 20000mAh', 'Car Charger', 'Bluetooth Speaker',
        'Phone Ring Holder', 'Selfie Stick', 'Screen Cleaning Kit', 'Car Phone Mount',
        'OTG Adapter', 'SIM Ejector Tool', 'Camera Lens Protector', 'Privacy Screen Protector',
    ];

    private const PART_TYPES = [
        'Screen Assembly', 'Battery', 'Charging Port Flex', 'Back Glass',
        'Rear Camera Module', 'Front Camera Module', 'Loudspeaker', 'Earpiece Speaker',
        'Vibration Motor', 'Power Button Flex',
    ];

    public function run(): void
    {
        $handsetCategory = ProductCategory::factory()->create(['name' => 'Handsets']);
        $accessoryCategory = ProductCategory::factory()->create(['name' => 'Accessories']);
        $partCategory = ProductCategory::factory()->create(['name' => 'Parts']);

        $deviceModels = collect();

        foreach (self::BRANDS as $brandName => $models) {
            $brand = DeviceBrand::factory()->create(['name' => $brandName]);

            foreach ($models as [$modelName, $year]) {
                $deviceModels->push(DeviceModel::factory()->create([
                    'device_brand_id' => $brand->id,
                    'name' => $modelName,
                    'release_year' => $year,
                ]));
            }
        }

        // 15 sellable handset SKUs, one per a subset of the seeded models.
        foreach ($deviceModels->take(15) as $model) {
            Product::factory()->handset()->create([
                'name' => $model->brand->name.' '.$model->name,
                'product_category_id' => $handsetCategory->id,
                'device_brand_id' => $model->device_brand_id,
            ]);
        }

        // 20 generic accessories, not device-specific.
        foreach (self::ACCESSORY_NAMES as $name) {
            Product::factory()->accessory()->create([
                'name' => $name,
                'product_category_id' => $accessoryCategory->id,
                'device_brand_id' => null,
            ]);
        }

        // 25 parts, each compatible with 1-3 of the seeded device models.
        for ($i = 0; $i < 25; $i++) {
            $model = $deviceModels->random();
            $part = Product::factory()->part()->create([
                'name' => $model->brand->name.' '.$model->name.' '.self::PART_TYPES[$i % count(self::PART_TYPES)],
                'product_category_id' => $partCategory->id,
                'device_brand_id' => $model->device_brand_id,
            ]);

            $part->compatibleDeviceModels()->attach(
                $deviceModels->random(random_int(1, 3))->pluck('id')->push($model->id)->unique(),
            );
        }

        collect([
            ['Screen Replacement', 'screen', 1200, 45, 30],
            ['Battery Replacement', 'battery', 900, 30, 30],
            ['Charging Port Repair', 'connector', 600, 45, 15],
            ['Water Damage Cleaning', 'water_damage', 800, 90, 7],
            ['Speaker Repair', 'audio', 500, 30, 15],
            ['Camera Replacement', 'camera', 1500, 45, 30],
            ['Diagnostic Checkup', 'diagnostics', 0, 20, 0],
            ['Software Flash / Unbrick', 'software', 700, 60, 7],
            ['Back Glass Replacement', 'body', 1000, 60, 15],
            ['Motherboard Micro-soldering', 'motherboard', 2500, 180, 15],
        ])->each(fn (array $s) => Service::factory()->create([
            'name' => $s[0], 'category' => $s[1], 'default_price' => $s[2],
            'default_duration_minutes' => $s[3], 'warranty_days' => $s[4],
        ]));
    }
}
