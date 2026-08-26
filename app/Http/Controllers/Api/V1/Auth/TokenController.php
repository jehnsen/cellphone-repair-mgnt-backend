<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Api\ApiException;
use App\Support\Api\ApiResponse;
use App\Support\Api\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Infra-only for Stage 2: issues Sanctum personal access tokens against the
 * stock Laravel User model. Stage 4 replaces this with the real identity
 * model (branch_id, employee_code, roles) and token-ability mirroring —
 * this proves the auth pipeline (bearer tokens, no sessions/cookies) works.
 */
class TokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw new ApiException(ErrorCode::Unauthenticated, 'These credentials do not match our records.');
        }

        $token = $user->createToken($data['device_name']);

        return ApiResponse::success([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
        ], status: 201);
    }

    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens->map(fn ($token) => [
            'id' => $token->id,
            'name' => $token->name,
            'last_used_at' => $token->last_used_at,
            'created_at' => $token->created_at,
        ]);

        return ApiResponse::success($tokens);
    }

    public function destroy(Request $request, int $tokenId): HttpResponse
    {
        $deleted = $request->user()->tokens()->where('id', $tokenId)->delete();

        if (! $deleted) {
            throw new ApiException(ErrorCode::NotFound, 'Token not found.');
        }

        return Response::noContent();
    }

    public function destroyCurrent(Request $request): HttpResponse
    {
        $request->user()->currentAccessToken()->delete();

        return Response::noContent();
    }
}
