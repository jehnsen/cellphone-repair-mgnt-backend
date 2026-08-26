<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Database\Factories\DeviceModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['device_brand_id', 'name', 'release_year', 'aliases', 'is_active'])]
class DeviceModel extends Model
{
    /** @use HasFactory<DeviceModelFactory> */
    use HasFactory, HasUlid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'aliases' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(DeviceBrand::class, 'device_brand_id');
    }

    public function customerDevices(): HasMany
    {
        return $this->hasMany(CustomerDevice::class);
    }

    public function compatibleParts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'part_compatibilities');
    }
}
