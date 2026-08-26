<?php

namespace App\Support\Api;

use Illuminate\Http\JsonResponse;

/**
 * Builds the two envelope shapes every route returns:
 * success  { data, meta?, links? }
 * error    { error: { code, message, details[] } }
 */
class ApiResponse
{
    public static function success(mixed $data, array $meta = [], array $links = [], int $status = 200): JsonResponse
    {
        $payload = ['data' => $data];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        if ($links !== []) {
            $payload['links'] = $links;
        }

        return response()->json($payload, $status);
    }

    public static function noContent(): \Symfony\Component\HttpFoundation\Response
    {
        return response()->noContent();
    }

    /**
     * For framework-level failures that don't map onto the fixed catalogue
     * in ErrorCode (see ErrorCode::fromHttpStatus). Still a valid envelope,
     * just an uncatalogued code — never rendered as HTML either way.
     */
    public static function rawError(string $code, string $message, array $details, int $status): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ], $status);
    }

    /** @param array<int, array<string, mixed>> $details */
    public static function error(ErrorCode $code, ?string $message = null, array $details = [], ?int $status = null): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code->value,
                'message' => $message ?? $code->defaultMessage(),
                'details' => $details,
            ],
        ], $status ?? $code->defaultStatus());
    }
}
