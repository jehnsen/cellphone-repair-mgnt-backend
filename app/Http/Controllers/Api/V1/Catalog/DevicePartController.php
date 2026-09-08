<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DevicePart\StoreDevicePartRequest;
use App\Http\Requests\Api\V1\DevicePart\UpdateDevicePartRequest;
use App\Http\Resources\DevicePartResource;
use App\Models\DevicePart;
use App\Services\DiagnosisVisualService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The parts of the generic phone rig the diagnosis visualizer draws.
 *
 * Shop-wide reference data, so no branch scoping — a battery is a battery at
 * either site, and the list is small enough to come back unpaginated in one
 * call (the client builds the whole scene from it).
 */
class DevicePartController extends Controller
{
    public function __construct(private readonly DiagnosisVisualService $diagnosis) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', DevicePart::class);

        // The visualizer wants only what it should draw; the admin screen
        // wants everything, so it can turn a part back on.
        $includeInactive = $request->boolean('include_inactive')
            && $request->user()->can('create', DevicePart::class);

        return DevicePartResource::collection($this->diagnosis->parts($includeInactive));
    }

    public function store(StoreDevicePartRequest $request): JsonResponse
    {
        $part = DevicePart::create($this->attributes($request->validated()));

        return (new DevicePartResource($part))->response()->setStatusCode(201);
    }

    public function update(UpdateDevicePartRequest $request, DevicePart $devicePart): DevicePartResource
    {
        $devicePart->update($this->attributes($request->validated()));

        return new DevicePartResource($devicePart->refresh());
    }

    /**
     * Flatten the nested position/size/explode objects the client sends into
     * the flat columns the table keeps. Nested on the wire because that is how
     * the scene reads them; flat in the schema because they are indexed and
     * ordered on, and a JSON blob would make that awkward.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        $attributes = collect($validated)
            ->except(['position', 'size', 'explode'])
            ->all();

        foreach (['position' => 'pos', 'size' => 'size'] as $field => $prefix) {
            if (isset($validated[$field])) {
                foreach (['x', 'y', 'z'] as $axis) {
                    $attributes[$prefix.'_'.$axis] = $validated[$field][$axis];
                }
            }
        }

        if (isset($validated['explode'])) {
            foreach (['x', 'y', 'z'] as $axis) {
                if (array_key_exists($axis, $validated['explode'])) {
                    $attributes['explode_'.$axis] = $validated['explode'][$axis];
                }
            }

            if (array_key_exists('distance', $validated['explode'])) {
                $attributes['explode_distance'] = $validated['explode']['distance'];
            }
        }

        return $attributes;
    }
}
