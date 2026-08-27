<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicVerificationResource;
use App\Services\PublicVerificationService;

class PublicVerificationController extends Controller
{
    public function __construct(private readonly PublicVerificationService $verification) {}

    public function show(string $token): PublicVerificationResource
    {
        return new PublicVerificationResource($this->verification->findByToken($token));
    }
}
