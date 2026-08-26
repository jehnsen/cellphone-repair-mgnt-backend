<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RepairTicket\StoreTicketPhotoRequest;
use App\Http\Resources\TicketPhotoResource;
use App\Models\RepairTicket;
use App\Models\TicketPhoto;
use App\Services\TicketPhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TicketPhotoController extends Controller
{
    public function __construct(private readonly TicketPhotoService $photos) {}

    public function index(RepairTicket $ticket): AnonymousResourceCollection
    {
        $this->authorize('view', $ticket);

        $photos = $this->photos->list($ticket)->each(
            fn (TicketPhoto $photo) => $photo->signed_url = $this->photos->signedUrl($photo)
        );

        return TicketPhotoResource::collection($photos);
    }

    public function store(StoreTicketPhotoRequest $request, RepairTicket $ticket): JsonResponse
    {
        $photo = $this->photos->upload($ticket, $request->file('photo'), $request->string('phase')->toString(), $request->user());
        $photo->signed_url = $this->photos->signedUrl($photo);

        return (new TicketPhotoResource($photo))->response()->setStatusCode(201);
    }
}
