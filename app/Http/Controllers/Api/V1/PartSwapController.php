<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RepairTicket\StorePartSwapRequest;
use App\Http\Resources\PartSwapResource;
use App\Models\PartSwap;
use App\Models\Product;
use App\Models\RepairTicket;
use App\Models\TicketPhoto;
use App\Services\PartSwapService;
use App\Services\TicketPhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PartSwapController extends Controller
{
    public function __construct(
        private readonly PartSwapService $swaps,
        private readonly TicketPhotoService $photos,
    ) {}

    public function index(RepairTicket $ticket): AnonymousResourceCollection
    {
        $this->authorize('view', $ticket);

        $swaps = $this->swaps->list($ticket)->each(fn (PartSwap $swap) => $this->attachRemovedPhotoUrl($swap));

        return PartSwapResource::collection($swaps);
    }

    public function store(StorePartSwapRequest $request, RepairTicket $ticket): JsonResponse
    {
        $data = $request->validated();
        $data['installed_product_id'] = Product::idFromUlid($data['installed_product_ulid']);
        $data['removed_photo_ref'] = $data['removed_photo_ulid'] ?? null;
        unset($data['installed_product_ulid'], $data['removed_photo_ulid']);

        $swap = $this->swaps->record($ticket, $data, $request->user());
        $this->attachRemovedPhotoUrl($swap->load('installedProduct'));

        return (new PartSwapResource($swap))->response()->setStatusCode(201);
    }

    private function attachRemovedPhotoUrl(PartSwap $swap): PartSwap
    {
        $photo = $swap->removed_photo_ref
            ? TicketPhoto::where('ulid', $swap->removed_photo_ref)->first()
            : null;

        $swap->removed_photo_url = $photo ? $this->photos->signedUrl($photo) : null;

        return $swap;
    }
}
