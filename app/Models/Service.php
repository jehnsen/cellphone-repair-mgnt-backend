<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'category', 'default_price', 'default_duration_minutes', 'warranty_days', 'is_active'])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, HasUlid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'default_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
