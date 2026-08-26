<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RepairTicket\StoreTicketLineRequest;
use App\Http\Resources\TicketLineResource;
use App\Models\Product;
use App\Models\RepairTicket;
use App\Models\Service;
use App\Services\RepairTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TicketLineController extends Controller
{
    public function __construct(private readonly RepairTicketService $tickets) {}

    public function index(RepairTicket $ticket): AnonymousResourceCollection
    {
        $this->authorize('view', $ticket);

        return TicketLineResource::collection($this->tickets->lines($ticket));
    }

    public function store(StoreTicketLineRequest $request, RepairTicket $ticket): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['product_ulid'])) {
            $data['product_id'] = Product::idFromUlid($data['product_ulid']);
        }
        if (isset($data['service_ulid'])) {
            $data['service_id'] = Service::idFromUlid($data['service_ulid']);
        }
        unset($data['product_ulid'], $data['service_ulid']);

        $line = $this->tickets->addLine($ticket, $data, $request->user());

        return (new TicketLineResource($line->load(['product', 'service'])))->response()->setStatusCode(201);
    }
}
