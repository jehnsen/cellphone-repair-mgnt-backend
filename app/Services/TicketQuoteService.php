<?php

namespace App\Services;

use App\Models\RepairTicket;
use App\Models\TicketQuote;
use App\Models\User;
use App\Support\TicketStateMachine;
use Illuminate\Support\Facades\DB;

class TicketQuoteService
{
    public function __construct(
        private readonly RepairTicketService $tickets,
        private readonly TicketEventRecorder $events,
    ) {}

    public function list(RepairTicket $ticket)
    {
        return $ticket->quotes()->latest('sent_at')->get();
    }

    /** Sending a quote from `diagnosed` auto-advances the ticket to `awaiting_approval`. */
    public function send(RepairTicket $ticket, array $data, User $actor): TicketQuote
    {
        return DB::transaction(function () use ($ticket, $data, $actor) {
            $quote = TicketQuote::create([
                'repair_ticket_id' => $ticket->id,
                'quoted_amount' => $data['quoted_amount'],
                'sent_at' => now(),
                'channel' => $data['channel'],
            ]);

            if (TicketStateMachine::canTransition($ticket->status, 'awaiting_approval')) {
                $this->tickets->transition($ticket, 'awaiting_approval', 'Quote sent.', $actor);
            }

            $this->events->record(
                $ticket,
                'quote_sent',
                $actor,
                note: "Quoted {$data['quoted_amount']} via {$data['channel']}.",
                metadata: ['quote_id' => $quote->id],
            );

            return $quote;
        });
    }

    /**
     * The approved amount locks the ticket total (module 3: "approved
     * amount locked to the ticket"). Approve advances the ticket toward
     * repair; decline returns the device as-is — only when those
     * transitions are actually legal from the ticket's current status,
     * since a quote can in principle be answered late.
     */
    public function respond(TicketQuote $quote, array $data, User $actor): TicketQuote
    {
        return DB::transaction(function () use ($quote, $data, $actor) {
            $quote->update([
                'responded_at' => now(),
                'decision' => $data['decision'],
                'responder_note' => $data['responder_note'] ?? null,
            ]);

            $ticket = $quote->repairTicket;

            if ($data['decision'] === 'approved') {
                $ticket->update(['approved_amount' => $quote->quoted_amount]);
                $ticket = $this->tickets->recalculateBalance($ticket);

                if (TicketStateMachine::canTransition($ticket->status, 'in_repair')) {
                    $this->tickets->transition($ticket, 'in_repair', 'Quote approved by customer.', $actor);
                }
            } elseif ($data['decision'] === 'declined' && TicketStateMachine::canTransition($ticket->status, 'returned_as_is')) {
                $this->tickets->transition($ticket, 'returned_as_is', 'Quote declined by customer.', $actor);
            }

            $this->events->record(
                $ticket,
                'quote_responded',
                $actor,
                note: $data['responder_note'] ?? null,
                metadata: ['quote_id' => $quote->id, 'decision' => $data['decision']],
            );

            return $quote->fresh();
        });
    }
}
