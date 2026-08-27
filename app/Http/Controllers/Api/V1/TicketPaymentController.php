<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RepairTicket\StoreTicketPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\RepairTicketResource;
use App\Models\RepairTicket;
use App\Repositories\Contracts\ShiftRepositoryInterface;
use App\Services\PaymentRecorder;
use App\Services\RepairTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * A repair ticket's balance is paid directly (payable_type=repair_ticket),
 * not through a Sale wrapper — sale_lines.sellable_type=ticket_balance
 * exists in the schema but has no column linking it back to a specific
 * ticket, so this endpoint is the actual path (see README).
 */
class TicketPaymentController extends Controller
{
    public function __construct(
        private readonly PaymentRecorder $payments,
        private readonly RepairTicketService $tickets,
        private readonly ShiftRepositoryInterface $shifts,
    ) {}

    public function index(RepairTicket $ticket): AnonymousResourceCollection
    {
        $this->authorize('view', $ticket);

        $payments = $ticket->payments()->with('actor')->latest()->get();

        return PaymentResource::collection($payments);
    }

    public function store(StoreTicketPaymentRequest $request, RepairTicket $ticket): JsonResponse
    {
        return DB::transaction(function () use ($request, $ticket) {
            $alreadyPaid = (float) $ticket->payments()->sum('amount') + (float) $ticket->downpayment;
            $base = (float) ($ticket->approved_amount ?? $ticket->estimated_cost ?? 0);
            $shift = $this->shifts->findOpenFor($request->user());

            $payment = $this->payments->record(
                'repair_ticket',
                $ticket->id,
                $base,
                $alreadyPaid,
                $request->validated(),
                $request->user(),
                $shift,
            );

            $ticket = $this->tickets->recalculateBalance($ticket);

            // Same reasoning as TicketQuoteController::respond(): ->resolve(),
            // not ->toArray() + a hand-rolled response — resolve() is what
            // actually runs JsonResource::filter(), which is what turns a
            // nested `new XResource(null)` (an unassigned technician, say)
            // into plain null instead of crashing on it.
            return response()->json([
                'data' => [
                    'payment' => (new PaymentResource($payment->load('actor')))->resolve($request),
                    'ticket' => (new RepairTicketResource($this->tickets->loadDisplayRelations($ticket)))->resolve($request),
                ],
            ], 201);
        });
    }
}
