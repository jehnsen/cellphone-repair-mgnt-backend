# Cellphone Repair Shop — Backend

JSON API backend for a cellphone repair shop management system: repair intake
and release, chain-of-custody proof, inventory across three item classes,
point of sale, buy-back/refurb, and reporting.

Stack: **Laravel 13, PHP 8.3+, MySQL 8.0 (InnoDB), Redis.**

Full domain design (ERDs, table catalogue, ticket state machine, error code
catalogue, endpoint list) lives in
[`docs/design/01-domain-design.md`](docs/design/01-domain-design.md). Read
that before touching migrations or routes — this README only covers running
the thing.

## Rule zero: JSON, always, no exceptions

This is a pure JSON API. There is no presentation layer of any kind — no
Blade views, no server-rendered HTML, no PDF/image generation, no session
cookies, no CSRF. Every route returns `application/json` or `204 No
Content`, including validation errors, 401/403/404/405/409/422/429/500, and
framework-level failures. See `bootstrap/app.php` for how this is enforced
(`ForceJsonResponse` middleware + a `shouldRenderJsonWhen(fn () => true)`
exception handler with no HTML fallback).

**Exception:** file downloads against a signed storage URL
(`storage/{path}`, added by Laravel's filesystem service provider) serve raw
bytes, not JSON — that's explicitly outside the API contract per the design
brief. Photo uploads still go in and come back out as JSON (a ULID + a
short-TTL signed URL); only the actual byte transfer for the download itself
is exempt.

**Exception:** Horizon and Telescope (once wired up in a later stage) are
ops tooling with their own UI, mounted behind a separate route prefix with
an IP allow-list and basic auth, and are outside the `/api/v1` surface.
Telescope is disabled in production.

## Auth

Stateless bearer tokens via Sanctum personal access tokens — no cookies, no
CSRF, no sessions. `POST /api/v1/auth/token` (email/password/device_name)
issues a token; `GET /api/v1/auth/tokens` lists the current user's tokens;
`DELETE /api/v1/auth/tokens/{id}` and `POST /api/v1/auth/logout` revoke one.

The `User` model backing this right now is the stock Laravel scaffold model.
It gets replaced in Stage 4 with the real identity model (`branch_id`,
`employee_code`, roles via spatie/laravel-permission) — the auth *pipeline*
(bearer tokens, no session state) is what Stage 2 proves out.

## Idempotency

Every write endpoint honors an `Idempotency-Key` header
(`App\Http\Middleware\EnsureIdempotencyKey`): a repeated key with the same
request body returns the original cached response for 24h; a repeated key
with a *different* body returns `409 IDEMPOTENCY_CONFLICT`. Sending the
header is optional per-request — omit it and nothing is deduplicated.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
composer test
```

### Current local environment caveats

- **Database engine:** this machine's XAMPP MySQL is actually **MariaDB
  10.4.32**, not MySQL 8.0 as the brief specifies. MariaDB has no
  `utf8mb4_0900_ai_ci` (a MySQL-8-only ICU collation), so the schema targets
  `utf8mb4_unicode_ci` instead — that's Laravel's own default, so no config
  override was needed. See
  [`docs/design/01-domain-design.md`](docs/design/01-domain-design.md) Flag
  13 for the full reasoning and what to double-check if production ever runs
  real MySQL 8 instead. Local `.env` connects to `cp_repair_db` on
  `root`@`localhost` with no password.
- **Redis:** required (cache, queue, and later Horizon). Runs via
  [predis/predis](https://github.com/predis/predis) (`REDIS_CLIENT=predis`)
  since no `ext-redis` is installed — no native extension needed. If
  `/api/v1/ready` reports `"redis": false`, Redis isn't running locally;
  start whatever's providing it on `127.0.0.1:6379` (this was a Docker
  container earlier in development — Docker Desktop needs to be running).
- **Composer/TLS:** if `composer require` fails with `SSL certificate
  problem: self-signed certificate`, PHP's curl/openssl don't have a CA
  bundle configured. Fixed here by downloading a fresh bundle and pointing
  `curl.cainfo` / `openssl.cafile` at it in `php.ini`.

## Testing

Pest. `composer test` (or `./vendor/bin/pest`) clears config cache and runs
the suite. Notable coverage so far:

- Every registered route returns a JSON content type
  (`tests/Feature/JsonOnlyApiTest.php`), including 401/404/405/422/500 paths.
- Idempotency replay and conflict behavior.
- Token issuance, listing, and revocation.
- Liveness/readiness endpoints against real dependencies.

## API surface (so far)

```
GET  /api/v1/health          liveness
GET  /api/v1/ready           readiness — checks DB, Redis, queue
POST /api/v1/auth/token      issue a bearer token
GET  /api/v1/auth/tokens     list the current user's tokens      (auth required)
DELETE /api/v1/auth/tokens/{id}   revoke one                     (auth required)
POST /api/v1/auth/logout     revoke the current token            (auth required)
```

Everything else in the design doc's endpoint list ships in later stages —
see `docs/design/01-domain-design.md` §8 for the build order.
