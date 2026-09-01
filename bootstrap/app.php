<?php

use App\Http\Middleware\EnsureIdempotencyKey;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\ResolveBranchContext;
use App\Support\Api\ApiException;
use App\Support\Api\ApiResponse;
use App\Support\Api\ErrorCode;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        // No `health: '/up'` — that framework default renders an HTML view
        // for non-JSON clients. GET /api/v1/health and /api/v1/ready are
        // this API's liveness/readiness routes and are always JSON.
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Rule Zero: every request is treated as wanting JSON, regardless of
        // what the client actually sent, so nothing can content-negotiate
        // its way into an HTML response.
        $middleware->append(ForceJsonResponse::class);

        // Branch scope must be resolved BEFORE SubstituteBindings, because
        // route-model binding queries branch-scoped models (e.g. the
        // {user} in /users/{user}) through BranchScope. Registered after
        // it, an owner's ?branch=all still 404s on a route-bound record
        // from another branch — the binding already ran under the default
        // own-branch scope. It resolves the user itself rather than
        // relying on auth:sanctum having run (see BranchContext).
        $middleware->prependToGroup('api', ResolveBranchContext::class);

        // Idempotency-Key support for every write endpoint. Safe to run for
        // every api request — it's a no-op unless the header is present.
        $middleware->api(append: [
            EnsureIdempotencyKey::class,
        ]);

        // Named 'api' limiter is defined in AppServiceProvider::boot().
        $middleware->throttleApi();

        // No CSRF middleware is registered at all — it only ever ships in
        // the 'web' group, and routes/web.php is intentionally empty, so it
        // never runs. There is no cookie-based auth to protect.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every throwable renders as JSON — validation, 401/403/404/405/409/
        // 422/429/500, framework-level failures, all of it. No HTML error
        // pages, no debug error page, ever.
        $exceptions->shouldRenderJsonWhen(fn () => true);

        $exceptions->render(function (ApiException $e) {
            return ApiResponse::error($e->errorCode(), $e->getMessage(), $e->details(), $e->status());
        });

        $exceptions->render(function (ValidationException $e) {
            $details = collect($e->errors())
                ->map(fn (array $messages, string $field) => ['field' => $field, 'messages' => $messages])
                ->values()
                ->all();

            return ApiResponse::error(ErrorCode::ValidationFailed, details: $details, status: $e->status);
        });

        $exceptions->render(function (AuthenticationException $e) {
            return ApiResponse::error(ErrorCode::Unauthenticated);
        });

        $exceptions->render(function (HttpExceptionInterface $e) {
            $status = $e->getStatusCode();
            $details = match (true) {
                $e instanceof MethodNotAllowedHttpException => [[
                    'allowed_methods' => explode(', ', $e->getHeaders()['Allow'] ?? ''),
                ]],
                $e instanceof TooManyRequestsHttpException && isset($e->getHeaders()['Retry-After']) => [[
                    'retry_after_seconds' => (int) $e->getHeaders()['Retry-After'],
                ]],
                default => [],
            };

            $code = ErrorCode::fromHttpStatus($status);

            if ($code === null) {
                return ApiResponse::rawError(
                    "HTTP_{$status}",
                    $e->getMessage() !== '' ? $e->getMessage() : 'An unexpected HTTP error occurred.',
                    $details,
                    $status,
                );
            }

            return ApiResponse::error($code, details: $details, status: $status);
        });

        // Absolute catch-all. A fatal on a POS terminal at 6pm on a Saturday
        // must return parseable JSON, not an HTML blob the client silently
        // fails on.
        $exceptions->render(function (Throwable $e) {
            return ApiResponse::error(
                ErrorCode::InternalError,
                config('app.debug') ? $e->getMessage() : null,
            );
        });
    })->create();
