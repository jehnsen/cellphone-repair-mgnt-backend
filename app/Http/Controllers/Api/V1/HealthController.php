<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Api\ApiResponse;
use App\Support\Api\ErrorCode;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthController extends Controller
{
    /** Liveness only — the process is up and answering requests. */
    public function health(): JsonResponse
    {
        return ApiResponse::success(['status' => 'up']);
    }

    /** Readiness — every dependency this API needs is actually reachable. */
    public function ready(): JsonResponse
    {
        $checks = [
            'database' => $this->probe(fn () => DB::connection()->getPdo() !== null),
            'redis' => $this->probe(fn () => Redis::connection()->ping() !== false),
            'queue' => $this->probe(fn () => is_int(Queue::size())),
        ];

        if (in_array(false, $checks, true)) {
            return ApiResponse::error(
                ErrorCode::ServiceUnavailable,
                details: [['dependency' => 'checks', 'status' => $checks]],
            );
        }

        return ApiResponse::success(['status' => 'ready', 'checks' => $checks]);
    }

    private function probe(Closure $check): bool
    {
        try {
            return (bool) $check();
        } catch (Throwable) {
            return false;
        }
    }
}
