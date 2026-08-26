<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Default limiter for the whole /api/v1 surface. Authenticated
        // requests are throttled per-user so one busy terminal doesn't
        // starve another sharing the same NAT'd IP; anonymous requests
        // fall back to IP.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // The public chain-of-custody verification endpoint is unauthenticated
        // by design, so it gets its own strict, IP-scoped limiter — see
        // docs/design/01-domain-design.md §2.5 / §6.
        RateLimiter::for('public-verify', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
