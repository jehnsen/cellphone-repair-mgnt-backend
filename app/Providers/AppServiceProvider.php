<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /** Name of the named rate limiter guarding POST /auth/token. */
    public const LOGIN_LIMITER = 'auth-token';

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

        // Credential-guessing limiter for POST /auth/token. That route sits
        // outside the auth group, so the 'api' limiter can only key it by IP
        // — 120 guesses/min against short employee-style credentials. Two
        // buckets, both of which must pass:
        //   - per email+IP, so one attacker can't grind a single account;
        //   - per IP, so they can't sidestep the first by spraying the same
        //     password across many emails.
        // Successful logins clear the email+IP bucket (see TokenController)
        // so a staff member who fat-fingers a password twice then logs in
        // isn't left throttled for the rest of the minute.
        RateLimiter::for(self::LOGIN_LIMITER, function (Request $request) {
            return [
                Limit::perMinute(5)->by(self::loginThrottleKey($request)),
                Limit::perMinute(20)->by('login-ip:'.$request->ip()),
            ];
        });

        // is_active is enforced in two places, because revoking access has
        // to cover both halves: TokenController::store blocks a deactivated
        // user from obtaining a *new* token, and this callback rejects the
        // ones they already hold. Without it a fired technician keeps API
        // access indefinitely — config/sanctum.php sets 'expiration' => null,
        // so an issued token otherwise never goes stale on its own.
        //
        // Runs inside Sanctum's Guard::isValidAccessToken() on every
        // authenticated request; returning false makes the guard resolve no
        // user at all, which surfaces as the standard 401 UNAUTHENTICATED.
        Sanctum::authenticateAccessTokensUsing(
            static function (PersonalAccessToken $accessToken, bool $isValid): bool {
                if (! $isValid) {
                    return false;
                }

                $tokenable = $accessToken->tokenable;

                // Only User is token-authenticated today; anything else is
                // left to Sanctum's own verdict rather than assumed active.
                if (! $tokenable instanceof User) {
                    return $isValid;
                }

                return (bool) $tokenable->is_active;
            }
        );
    }

    /**
     * Throttle bucket for one credential pair: the submitted email plus the
     * source IP. Lower-cased so casing variants can't buy extra attempts.
     *
     * This is the *inner* key handed to Limit::by(). ThrottleRequests wraps
     * it before it reaches the cache — see loginThrottleCacheKey().
     */
    public static function loginThrottleKey(Request $request): string
    {
        return 'login:'.Str::lower(trim((string) $request->input('email'))).'|'.$request->ip();
    }

    /**
     * The key the throttle middleware actually stores this bucket under.
     *
     * ThrottleRequests rewrites every named-limiter key as
     * md5($limiterName.$key), so RateLimiter::clear() on the raw
     * loginThrottleKey() would silently clear nothing. Mirrored here rather
     * than duplicated at the call site so the two stay together if the
     * limiter name ever changes.
     *
     * Assumes ThrottleRequests key hashing is on, which is the framework
     * default and is never disabled here; ThrottleRequests exposes no getter
     * to assert that against. LoginThrottleTest covers the clear-on-success
     * path, so this going stale fails a test rather than quietly weakening
     * the limiter.
     */
    public static function loginThrottleCacheKey(Request $request): string
    {
        return md5(self::LOGIN_LIMITER.self::loginThrottleKey($request));
    }
}
