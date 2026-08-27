<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RepairTicket\StoreRepairTicketRequest;
use App\Http\Requests\Api\V1\RepairTicket\TransitionTicketRequest;
use App\Http\Requests\Api\V1\RepairTicket\UpdateRepairTicketRequest;
use App\Http\Resources\RepairTicketResource;
use App\Http\Resources\TicketEventResource;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\RepairTicket;
use App\Models\User;
use App\Services\RepairTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RepairTicketController extends Controller
{
    public function __construct(private readonly RepairTicketService $tickets) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', RepairTicket::class);

        return RepairTicketResource::collection($this->tickets->list());
    }

    public function store(StoreRepairTicketRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['branch_id'] = Branch::idFromUlid($data['branch_ulid']);
        $data['customer_id'] = Customer::idFromUlid($data['customer_ulid']);
        $data['customer_device_id'] = CustomerDevice::idFromUlid($data['customer_device_ulid']);

        if (isset($data['assigned_technician_ulid'])) {
            $data['assigned_technician_id'] = User::idFromUlid($data['assigned_technician_ulid']);
        }

        unset($data['branch_ulid'], $data['customer_ulid'], $data['customer_device_ulid'], $data['assigned_technician_ulid']);

        $ticket = $this->tickets->create($data, $request->user());

        return (new RepairTicketResource($this->tickets->loadDisplayRelations($ticket)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(RepairTicket $ticket): RepairTicketResource
    {
        $this->authorize('view', $ticket);

        $this->tickets->loadDisplayRelations($ticket);

        return new RepairTicketResource($ticket->load('lines', 'finding'));
    }

    public function update(UpdateRepairTicketRequest $request, RepairTicket $ticket): RepairTicketResource
    {
        $data = $request->validated();

        if (isset($data['assigned_technician_ulid'])) {
            $data['assigned_technician_id'] = User::idFromUlid($data['assigned_technician_ulid']);
        }
        unset($data['assigned_technician_ulid']);

        $ticket = $this->tickets->update($ticket, $data, $request->user());

        return new RepairTicketResource($this->tickets->loadDisplayRelations($ticket));
    }

    public function transition(TransitionTicketRequest $request, RepairTicket $ticket): RepairTicketResource
    {
        $data = $request->validated();

        $ticket = $this->tickets->transition($ticket, $data['to_status'], $data['note'] ?? null, $request->user());

        return new RepairTicketResource($this->tickets->loadDisplayRelations($ticket));
    }

    public function events(RepairTicket $ticket): AnonymousResourceCollection
    {
        $this->authorize('view', $ticket);

        return TicketEventResource::collection($this->tickets->events($ticket));
    }
}
