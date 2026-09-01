<?php

namespace App\Http\Middleware;

use App\Support\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the request's branch scope once, before any query runs, so
 * BranchScope has an answer ready (see BranchContext for the rules).
 *
 * Registered after Sanctum's auth middleware in bootstrap/app.php —
 * resolution depends on the authenticated user, so it cannot run earlier.
 */
class ResolveBranchContext
{
    public function __construct(private readonly BranchContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->context->resolve($request);

        return $next($request);
    }
}
