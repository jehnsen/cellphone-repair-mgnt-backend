<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Database\Factories\CustomerDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['customer_id', 'device_model_id', 'imei_normalized', 'serial_number', 'color', 'notes'])]
class CustomerDevice extends Model
{
    /** @use HasFactory<CustomerDeviceFactory> */
    use HasFactory, HasUlid;

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function deviceModel(): BelongsTo
    {
        return $this->belongsTo(DeviceModel::class);
    }

    public function repairTickets(): HasMany
    {
        return $this->hasMany(RepairTicket::class);
    }

    /**
     * Repair history for this physical device across every customer who
     * ever brought it in — not scoped to $this->customer_id. See
     * docs/design/01-domain-design.md Flag 6.
     */
    public static function historyForImei(string $imeiNormalized)
    {
        return RepairTicket::whereIn(
            'customer_device_id',
            self::where('imei_normalized', $imeiNormalized)->pluck('id'),
        );
    }
}
