<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * This is a pure JSON API (Rule Zero) — every request is treated as if it
 * asked for JSON, regardless of what the client actually sent, so nothing
 * in the framework can content-negotiate its way into an HTML response.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
