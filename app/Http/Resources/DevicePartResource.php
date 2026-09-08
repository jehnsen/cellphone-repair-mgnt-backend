<?php

namespace App\Http\Resources;

use App\Models\DevicePart;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A part of the generic phone rig, geometry included — the client builds the
 * 3D scene from this rather than shipping a hardcoded model, so adding a part
 * is a row rather than a deploy.
 *
 * @mixin DevicePart
 */
class DevicePartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'key' => $this->key,
            'label' => $this->label,
            'category' => $this->category,
            'blurb' => $this->blurb,
            // Millimetres against a nominal 150 x 72 x 8 mm handset, origin at
            // the centre of the slab (see the migration).
            'position' => [
                'x' => $this->pos_x,
                'y' => $this->pos_y,
                'z' => $this->pos_z,
            ],
            'size' => [
                'x' => $this->size_x,
                'y' => $this->size_y,
                'z' => $this->size_z,
            ],
            'explode' => [
                'x' => $this->explode_x,
                'y' => $this->explode_y,
                'z' => $this->explode_z,
                'distance' => $this->explode_distance,
            ],
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
