<?php

namespace App\Http\Middleware;

use App\Support\Api\ApiException;
use App\Support\Api\ErrorCode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every write endpoint accepts an Idempotency-Key header and returns the
 * original response for a repeat key within 24h — the POS runs offline over
 * flaky connections and will retry (see docs/design/01-domain-design.md).
 *
 * Opt-in per request: a client that doesn't send the header gets normal,
 * non-deduplicated behavior. GET/HEAD/OPTIONS are never deduplicated.
 */
class EnsureIdempotencyKey
{
    private const TTL_SECONDS = 86400;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if ($key === null || $request->isMethodSafe()) {
            return $next($request);
        }

        $cacheKey = $this->cacheKey($request, $key);
        $bodyHash = $this->hashBody($request);
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            if ($cached['body_hash'] !== $bodyHash) {
                throw new ApiException(ErrorCode::IdempotencyConflict);
            }

            return response()->json($cached['body'], $cached['status']);
        }

        /** @var Response $response */
        $response = $next($request);

        if ($response->getStatusCode() < 500 && $response instanceof \Illuminate\Http\JsonResponse) {
            Cache::put($cacheKey, [
                'body_hash' => $bodyHash,
                'status' => $response->getStatusCode(),
                'body' => $response->getData(true),
            ], self::TTL_SECONDS);
        }

        return $response;
    }

    private function cacheKey(Request $request, string $key): string
    {
        return sprintf('idempotency:%s:%s:%s', $request->method(), $request->path(), $key);
    }

    private function hashBody(Request $request): string
    {
        return hash('sha256', $request->getContent());
    }
}
