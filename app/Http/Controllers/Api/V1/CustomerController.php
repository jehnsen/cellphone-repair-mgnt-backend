<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\StoreCustomerRequest;
use App\Http\Requests\Api\V1\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Branch;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customers) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);

        return CustomerResource::collection($this->customers->list());
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['branch_id'] = Branch::idFromUlid($data['branch_ulid']);
        unset($data['branch_ulid']);

        $customer = $this->customers->create($data);

        return (new CustomerResource($customer->load('branch')))->response()->setStatusCode(201);
    }

    public function show(Customer $customer): CustomerResource
    {
        $this->authorize('view', $customer);

        return new CustomerResource($customer->load('branch'));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): CustomerResource
    {
        $data = $request->validated();

        if (isset($data['branch_ulid'])) {
            $data['branch_id'] = Branch::idFromUlid($data['branch_ulid']);
            unset($data['branch_ulid']);
        }

        $customer = $this->customers->update($customer, $data);

        return new CustomerResource($customer->load('branch'));
    }

    public function destroy(Customer $customer): Response
    {
        $this->authorize('delete', $customer);

        $this->customers->delete($customer);

        return response()->noContent();
    }
}
