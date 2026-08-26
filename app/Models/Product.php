<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'sku', 'barcode', 'name', 'product_category_id', 'device_brand_id', 'type',
    'cost', 'selling_price', 'is_serialized', 'reorder_point', 'track_inventory', 'is_active',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasUlid, SoftDeletes;

    public const TYPES = ['handset', 'accessory', 'part'];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'is_serialized' => 'boolean',
            'track_inventory' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(DeviceBrand::class, 'device_brand_id');
    }

    public function compatibleDeviceModels(): BelongsToMany
    {
        return $this->belongsToMany(DeviceModel::class, 'part_compatibilities');
    }

    public function serializedUnits(): HasMany
    {
        return $this->hasMany(SerializedUnit::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
