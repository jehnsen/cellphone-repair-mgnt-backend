<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Branch\StoreBranchRequest;
use App\Http\Requests\Api\V1\Branch\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Services\BranchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BranchController extends Controller
{
    public function __construct(private readonly BranchService $branches) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Branch::class);

        return BranchResource::collection($this->branches->list());
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        $branch = $this->branches->create($request->validated());

        return (new BranchResource($branch))->response()->setStatusCode(201);
    }

    public function show(Branch $branch): BranchResource
    {
        $this->authorize('view', $branch);

        return new BranchResource($branch);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): BranchResource
    {
        $branch = $this->branches->update($branch, $request->validated());

        return new BranchResource($branch);
    }
}
