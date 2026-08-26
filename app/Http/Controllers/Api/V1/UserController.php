<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\StoreUserRequest;
use App\Http\Requests\Api\V1\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Branch;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        return UserResource::collection($this->users->list());
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['branch_id'] = Branch::idFromUlid($data['branch_ulid']);
        unset($data['branch_ulid']);

        $user = $this->users->create($data);

        return (new UserResource($user->load('branch')))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user->load('branch'));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $data = $request->validated();

        if (isset($data['branch_ulid'])) {
            $data['branch_id'] = Branch::idFromUlid($data['branch_ulid']);
            unset($data['branch_ulid']);
        }

        $user = $this->users->update($user, $data);

        return new UserResource($user->load('branch'));
    }

    public function destroy(User $user): Response
    {
        $this->authorize('delete', $user);

        $this->users->delete($user);

        return response()->noContent();
    }
}
