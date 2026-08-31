<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\StoreCreditAdjustRequest;
use App\Http\Resources\StoreCreditAccountResource;
use App\Http\Resources\StoreCreditEntryResource;
use App\Models\Customer;
use App\Models\StoreCreditAccount;
use App\Services\StoreCreditService;
use Illuminate\Http\JsonResponse;

/**
 * A customer's shop-wide store-credit balance and ledger. Credit lands here
 * from `store_credit` refunds and manual manager adjustments, and is drawn
 * down by `store_credit` payments on sales / repair tickets.
 */
class StoreCreditController extends Controller
{
    public function __construct(private readonly StoreCreditService $storeCredit) {}

    public function show(Customer $customer): StoreCreditAccountResource
    {
        $this->authorize('view', StoreCreditAccount::class);

        $account = $this->storeCredit->accountFor($customer);
        $account->load([
            'customer',
            'entries' => fn ($query) => $query->with('actor')->latest('id')->limit(50),
        ]);

        return new StoreCreditAccountResource($account);
    }

    public function adjust(StoreCreditAdjustRequest $request, Customer $customer): JsonResponse
    {
        $data = $request->validated();

        $entry = $data['direction'] === 'credit'
            ? $this->storeCredit->issue($customer, (float) $data['amount'], $data['reason'], $request->user(), 'manual_adjustment')
            : $this->storeCredit->redeem($customer, (float) $data['amount'], $data['reason'], $request->user(), 'manual_adjustment');

        return (new StoreCreditEntryResource($entry->load('actor')))->response()->setStatusCode(201);
    }
}
