<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Database\Factories\DeviceBrandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'logo_ref', 'is_active'])]
class DeviceBrand extends Model
{
    /** @use HasFactory<DeviceBrandFactory> */
    use HasFactory, HasUlid, SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function deviceModels(): HasMany
    {
        return $this->hasMany(DeviceModel::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
