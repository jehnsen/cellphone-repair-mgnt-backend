<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'contact_name', 'contact_phone', 'contact_email', 'terms', 'notes', 'is_active'])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory, HasUlid, SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
