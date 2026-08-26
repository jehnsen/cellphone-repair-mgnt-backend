<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RepairTicket\RespondTicketQuoteRequest;
use App\Http\Requests\Api\V1\RepairTicket\StoreTicketQuoteRequest;
use App\Http\Resources\RepairTicketResource;
use App\Http\Resources\TicketQuoteResource;
use App\Models\RepairTicket;
use App\Models\TicketQuote;
use App\Services\RepairTicketService;
use App\Services\TicketQuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TicketQuoteController extends Controller
{
    public function __construct(
        private readonly TicketQuoteService $quotes,
        private readonly RepairTicketService $tickets,
    ) {}

    public function index(RepairTicket $ticket): AnonymousResourceCollection
    {
        $this->authorize('view', $ticket);

        return TicketQuoteResource::collection($this->quotes->list($ticket));
    }

    public function store(StoreTicketQuoteRequest $request, RepairTicket $ticket): JsonResponse
    {
        $quote = $this->quotes->send($ticket, $request->validated(), $request->user());

        return (new TicketQuoteResource($quote))->response()->setStatusCode(201);
    }

    public function respond(RespondTicketQuoteRequest $request, RepairTicket $ticket, TicketQuote $quote): JsonResponse
    {
        abort_if($quote->repair_ticket_id !== $ticket->id, 404);

        $quote = $this->quotes->respond($quote, $request->validated(), $request->user());

        // ->resolve(), not ->toArray(): resolve() runs the filter() pass
        // that turns a nested `new XResource(null)` (e.g. an unassigned
        // technician) into `null` instead of crashing on it — toArray()
        // skips that pass entirely.
        return response()->json([
            'data' => [
                'quote' => (new TicketQuoteResource($quote))->resolve($request),
                'ticket' => (new RepairTicketResource($this->tickets->loadDisplayRelations($ticket->fresh())))->resolve($request),
            ],
        ]);
    }
}
