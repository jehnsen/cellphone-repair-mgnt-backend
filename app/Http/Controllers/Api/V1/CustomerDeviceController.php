<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CustomerDevice\StoreCustomerDeviceRequest;
use App\Http\Requests\Api\V1\CustomerDevice\UpdateCustomerDeviceRequest;
use App\Http\Resources\CustomerDeviceResource;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\DeviceModel;
use App\Services\CustomerDeviceService;
use App\Support\Imei;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class CustomerDeviceController extends Controller
{
    public function __construct(private readonly CustomerDeviceService $devices) {}

    public function index(Customer $customer): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CustomerDevice::class);

        return CustomerDeviceResource::collection($this->devices->listForCustomer($customer));
    }

    public function store(StoreCustomerDeviceRequest $request, Customer $customer): JsonResponse
    {
        $data = $this->resolveUlidReferences($request->validated());

        $device = $this->devices->create($customer, $data);

        return (new CustomerDeviceResource($device->load('deviceModel')))->response()->setStatusCode(201);
    }

    public function show(Customer $customer, CustomerDevice $device): CustomerDeviceResource
    {
        $this->authorize('view', $device);
        $this->assertBelongsToCustomer($customer, $device);

        return new CustomerDeviceResource($device->load('deviceModel'));
    }

    public function update(UpdateCustomerDeviceRequest $request, Customer $customer, CustomerDevice $device): CustomerDeviceResource
    {
        $this->assertBelongsToCustomer($customer, $device);

        $data = $this->resolveUlidReferences($request->validated());

        $device = $this->devices->update($device, $data);

        return new CustomerDeviceResource($device->load('deviceModel'));
    }

    public function destroy(Customer $customer, CustomerDevice $device): Response
    {
        $this->authorize('delete', $device);
        $this->assertBelongsToCustomer($customer, $device);

        $this->devices->delete($device);

        return response()->noContent();
    }

    /**
     * The flagship differentiator: GET /devices/by-imei/{imei} — repair
     * history for this physical device across every customer who ever
     * brought it in, not scoped to a single customer.
     */
    public function historyByImei(string $imei): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CustomerDevice::class);

        return CustomerDeviceResource::collection(
            $this->devices->historyByImei(Imei::normalize($imei))
        );
    }

    private function resolveUlidReferences(array $data): array
    {
        if (array_key_exists('imei', $data)) {
            $data['imei_normalized'] = $data['imei'];
            unset($data['imei']);
        }

        if (isset($data['device_model_ulid'])) {
            $data['device_model_id'] = DeviceModel::idFromUlid($data['device_model_ulid']);
        }
        unset($data['device_model_ulid']);

        return $data;
    }

    private function assertBelongsToCustomer(Customer $customer, CustomerDevice $device): void
    {
        abort_if($device->customer_id !== $customer->id, 404);
    }
}
