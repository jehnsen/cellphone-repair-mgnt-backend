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

A Postman collection covering every endpoint implemented so far — with
auto-captured ULIDs chaining each Create into the Show/Update requests below
it, a working Idempotency-Key demo, and a self-cleaning teardown folder —
lives at
[`docs/postman/Cellphone-Repair-Shop-API.postman_collection.json`](docs/postman/Cellphone-Repair-Shop-API.postman_collection.json).
Import it, run **Auth → Issue Token** first, and the rest works out of the
box against a freshly seeded database. Verified end-to-end with `newman`
(`npx newman run docs/postman/Cellphone-Repair-Shop-API.postman_collection.json`).

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

`User` is now the real identity model (`branch_id`, `employee_code`, soft
deletes, ULID) with roles via spatie/laravel-permission
(`owner`/`manager`/`cashier`/`technician`, permissions listed in
`RoleAndPermissionSeeder`). Policy/gate *enforcement* of those permissions in
the API layer is still Stage 4 — right now the roles exist and are seeded,
but nothing checks them yet.

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
php artisan migrate --seed
composer test
```

`migrate --seed` (or `migrate:fresh --seed` from empty) builds the full
schema — ~50 tables across every bounded context in the domain design — and
loads a demo shop: 2 branches, 8 users across all four roles, 25 real
PH-market device models, 60 products (15 handsets / 20 accessories / 25
parts), 40 serialized handset units with a reconciled opening stock ledger,
25 customers, 120 repair tickets spread across all 11 statuses, 90 days of
sales with a full shift history per branch, plus a handful of buy-back,
installment, message-template, and commission-rule records. See
`database/seeders/DatabaseSeeder.php` for the full list.

### Current local environment caveats

- **Database engine:** this machine's XAMPP MySQL is actually **MariaDB
  10.4.32**, not MySQL 8.0 as the brief specifies. MariaDB has no
  `utf8mb4_0900_ai_ci` (a MySQL-8-only ICU collation), so the schema targets
  `utf8mb4_unicode_ci` instead — that's Laravel's own default, so no config
  override was needed. See
  [`docs/design/01-domain-design.md`](docs/design/01-domain-design.md) Flag
  13 for the full reasoning and what to double-check if production ever runs
  real MySQL 8 instead. Local `.env` connects to `cp_repair_db` on
  `root`@`localhost` with no password; the Pest suite uses a separate
  `cp_repair_db_testing` database (see `phpunit.xml`) so `RefreshDatabase`
  never touches the seeded demo data. Both need to exist — `CREATE DATABASE`
  them if they don't.
- **Redis:** required (cache, queue, and later Horizon). Runs via
  [predis/predis](https://github.com/predis/predis) (`REDIS_CLIENT=predis`)
  since no `ext-redis` is installed — no native extension needed. It's
  currently **not running** on this machine (nothing on it can serve 6379 —
  no service, no reachable Docker engine), so local `.env` falls back to the
  `database` cache/queue driver for now; `.env.example` still documents
  Redis as the target. If `/api/v1/ready` reports `"redis": false`, that's
  this — start whatever's providing it on `127.0.0.1:6379` (Docker Desktop
  needs to be running if it's a container) and switch `.env` back to
  `CACHE_STORE=redis` / `QUEUE_CONNECTION=redis`.
- **Composer/TLS:** if `composer require` fails with `SSL certificate
  problem: self-signed certificate`, PHP's curl/openssl don't have a CA
  bundle configured. Fixed here by downloading a fresh bundle and pointing
  `curl.cainfo` / `openssl.cafile` at it in `php.ini`.

## Testing

Pest, against the real `cp_repair_db_testing` MariaDB database (not SQLite —
the schema uses FULLTEXT indexes and CHECK constraints SQLite can't
faithfully validate; see `phpunit.xml`). `composer test` (or `./vendor/bin/pest`)
clears config cache and runs the suite. Notable coverage so far:

- Every registered route returns a JSON content type
  (`tests/Feature/JsonOnlyApiTest.php`), including 401/404/405/422/500 paths.
- Idempotency replay and conflict behavior.
- Token issuance, listing, and revocation.
- Liveness/readiness endpoints against real dependencies (readiness will
  correctly report `503` while Redis is down locally — see above).
- Repair tickets: ULID resolution on create, legal/illegal state
  transitions (including the `409` vs `422` distinction), edits locked once
  `released`, the release permission gate, margin/unlock field visibility by
  role, line items (part vs. labor, cross-field validation), photo upload
  with a signed URL response, and the quote send/approve/decline flow
  including its auto-transitions (`tests/Feature/Api/V1/RepairTicketTest.php`,
  `TicketLineTest.php`, `TicketPhotoTest.php`, `TicketQuoteTest.php`).

## Data layer

Every table in the domain design has a migration, an Eloquent model, and a
factory (`app/Models/`, `database/migrations/`, `database/factories/`).
Conventions applied consistently across all of them:

- **ULIDs**: any model with its own route/document identity uses the
  `App\Models\Concerns\HasUlid` trait — auto-generates a `ulid` column on
  create and makes it the route-model-binding key. Internal `id` stays
  `BIGINT AUTO_INCREMENT` and is never exposed.
- **Branch scoping**: models with a `branch_id` use
  `#[ScopedBy(BranchScope::class)]` (a global scope, PHP 8.3 attribute —
  Laravel 13's replacement for a `booted()` override) — scopes queries to
  `Auth::user()->branch_id` when authenticated, a no-op otherwise (console,
  seeders, cross-branch jobs). This is the "small change later" hook the
  brief asks for if multi-tenancy ever needs to be more than a column.
  `App\Models\Concerns\BelongsToBranch` adds the matching `branch()`
  relation.
- **Append-only ledgers** (`stock_movements`, `ticket_events`, `payments`,
  `commission_entries`, etc.) set `const UPDATED_AT = null` and are never
  given an `update()` call anywhere in the seeders — only ever `create()`.
- **Money** is `decimal:2` cast everywhere; nothing is a float.
- **CHECK constraints** (products' cost/price non-negative, serialized
  units needing an IMEI or serial, ticket lines matching their `line_type`)
  are added via raw `DB::statement` in the migrations, since Laravel's
  schema builder has no first-class CHECK support yet — see
  `2026_08_26_030000_create_catalog_tables.php` for the pattern.
- **Gapless document numbers**: `App\Models\Sequence::next()` implements the
  `SELECT ... FOR UPDATE` counter from the design doc, with a retry against a
  genuine first-row race (two branches creating the same year/month counter
  simultaneously). Ticket/sale numbers embed the branch's `code` column
  (`JO-QC-202608-0001`) precisely because the counter is per-branch — a
  bug caught while seeding: two branches in the same month both produced
  `JO-202608-0001` before `code` was added.

## API layer: Controller → Service → Repository

Every endpoint follows the same three-layer split, plus a dedicated Request
and Response class per action:

- **Repository** (`app/Repositories/`) — the only place that talks to
  Eloquent. `BaseRepository` implements a generic `RepositoryInterface`
  (`all`, `paginate`, `find`, `findByUlid`, `create`, `update`, `delete`)
  using [spatie/laravel-query-builder](https://spatie.be/docs/laravel-query-builder)
  for filtering/sorting against an explicit per-model allow-list (Rule:
  "filtering via spatie/laravel-query-builder with explicit allow-lists").
  Concrete repositories only override the allow-lists and add model-specific
  finders (`CustomerRepository::findByMobile()`,
  `CustomerDeviceRepository::findAllByImei()`). Bound to their interfaces in
  `App\Providers\RepositoryServiceProvider`.
- **Service** (`app/Services/`) — business logic and orchestration, injected
  with a repository *interface* (never the concrete class). This is the only
  layer allowed to call `->update()`/`->create()` (Rule 10: no raw
  `$model->update()` from a controller).
- **Controller** (`app/Http/Controllers/Api/V1/`) — thin. Validates via a
  Form Request, resolves any client-supplied ULIDs to internal ids (see
  below), calls the service, returns a Resource. Authorization for
  `index`/`show` goes through `$this->authorize()` against a Policy; `store`/
  `update`/`destroy` delegate to the Form Request's own `authorize()` so the
  check happens before the controller body even runs.
- **Request** (`app/Http/Requests/Api/V1/{Model}/Store*.php`,
  `Update*.php`) — one pair per model, every one of them.
- **Resource** (`app/Http/Resources/`) — one per model, the only thing that
  ever gets serialized to JSON. `ProductResource` is the concrete example of
  "cost/margin fields conditionally included by permission": `cost` is
  wrapped in `$this->when($request->user()->can('reports.margin.view'), ...)`
  and disappears from the JSON entirely for a cashier.

**Foreign keys in request bodies are ULIDs, never internal ids** — Rule 6
("never expose sequential ids... in URLs, document numbers, or QR payloads")
applies to request bodies too, not just URLs. A client sends
`"branch_ulid": "01J..."`; the controller resolves it via
`Branch::idFromUlid()` (`App\Models\Concerns\HasUlid::idFromUlid()`) before
it ever reaches the service or repository. Route *path* parameters don't
need this — Laravel's route-model-binding already resolves
`Branch $branch` from `{branch}` using the ulid automatically.

**Policies** (`app/Policies/`) — one per model, checked against the
permissions seeded in `RoleAndPermissionSeeder`. `catalog.view` gates read
access to all five catalog models via a shared `AuthorizesCatalog` trait;
everything else is bespoke per model (e.g. `UserPolicy::delete` also blocks
a user deleting themself).

Master data implemented so far: branches (no `destroy` — closing a branch
is `is_active = false` via `update`, not a row removal, per Flag 1), users,
the five catalog models, customers, and customer devices (nested under
`/customers/{customer}/devices`, plus the flagship
`GET /devices/by-imei/{imei}` cross-customer, cross-branch history lookup).
Stage 5 adds repair tickets, their state machine, timeline, line items,
photos, and quotes (below). Every one of these routes, plus the Stage 2
auth/health routes, is listed by `php artisan route:list`. Inventory, POS,
and the rest of the domain follow the same pattern in later stages — see
`docs/design/01-domain-design.md` §8 for the build order.

## Repairs: tickets, state machine, timeline, photos, quotes (Stage 5)

`RepairTicket` (`/tickets`, no `destroy` — a ticket only ever moves through
its state machine, never gets removed) plus four nested resources:

- **State machine** (`App\Support\TicketStateMachine`) — the fixed
  transition graph from `docs/design/01-domain-design.md` §4, enforced by
  `POST /tickets/{ticket}/transition`. An illegal transition throws
  `ApiException(ErrorCode::InvalidStatusTransition, ...)`, which renders as
  **`409`**, not `422` — this is a state conflict, not a validation failure;
  see the error code catalogue. The transition itself row-locks the ticket
  (`lockForUpdate()`) before checking the graph, so two concurrent
  transition requests can't both succeed.
- **Timeline** (`GET /tickets/{ticket}/events`) — an append-only
  `ticket_events` ledger (`App\Services\TicketEventRecorder`), cursor-paginated
  per the design brief's "cursor pagination on ledger/timeline endpoints"
  rule (`cursorPaginate()`, not `paginate()`).
- **Line items** (`GET|POST /tickets/{ticket}/lines`) — `part` or `labor`,
  matching the DB CHECK constraint (`product_id` xor `service_id`) at the
  Form Request layer too. `unit_cost` is gated by `reports.margin.view`, same
  as `ProductResource::cost`.
- **Photos** (`GET|POST /tickets/{ticket}/photos`) — Rule Zero for binary:
  the upload is multipart in, but the response is always JSON — a ULID plus
  a 15-minute signed URL (`Storage::disk('local')->temporaryUrl()`), never
  the image bytes. The controller sets `$photo->signed_url` as a virtual
  attribute before wrapping it in a Resource, rather than the Resource
  reaching into a Service itself.
- **Quotes** (`GET|POST /tickets/{ticket}/quotes`,
  `POST .../quotes/{quote}/respond`) — sending a quote auto-advances
  `diagnosed → awaiting_approval`; an `approved` decision locks
  `approved_amount` onto the ticket, recalculates its balance, and
  auto-advances to `in_repair`; `declined` auto-advances to
  `returned_as_is` — each auto-transition only fires if it's still legal
  from the ticket's *current* status, since a quote can be answered late.

Two guards the `ready_for_pickup → released` edge still needs are
deliberately not enforced yet, per `TicketStateMachine`'s docblock: a
matching IMEI verification (Stage 6, chain of custody) and a settled balance
(Stage 8, POS). Stock consumption for `part` ticket lines isn't wired to the
inventory ledger yet either (Stage 7).

### Two more real bugs this stage surfaced

**A ticket's customer/technician can legitimately sit in another branch.**
`RepairTicket` is branch-scoped, but a repeat customer (or a technician
covering another branch) may not share the ticket's own branch — the same
scenario the IMEI-history lookup above already had to handle. Eager-loading
`customer`/`assignedTechnician` the normal way meant `BranchScope` silently
nulled them out whenever that happened, which the next bug turned from
"silently wrong" into a crash. Fixed in one place —
`RepairTicketService::loadDisplayRelations()` — with
`withoutGlobalScopes()` on those two relations, used by every controller
action and by `TicketPhotoService` for `captured_by`.

**`new SomeResource($this->whenLoaded('nullableRelation'))` needs `resolve()`,
not `toArray()`, to serialize safely.** Laravel's resource pipeline has a
built-in safety net for exactly this pattern — `whenLoaded()` on a relation
that's loaded but empty (an unassigned `assigned_technician`, for instance)
returns `null`, and `JsonResource::resolve()`'s `filter()` step turns a
nested `new XResource(null)` into plain `null` before it's ever serialized.
That safety net only runs on the `resolve()`/`response()` path, though —
`TicketQuoteController::respond()` originally built its two-resource
response by calling `->toArray($request)` directly and hand-assembling the
result into `response()->json([...])`, which skips `filter()` entirely and
crashes the moment `json_encode()` reaches the null-wrapped nested resource.
Fixed by calling `->resolve($request)` instead — same output, but through
the path that actually filters. Caught by
`tests/Feature/Api/V1/TicketQuoteTest.php`.

### A real bug this surfaced: BranchScope and cross-branch lookups

`CustomerDeviceRepository::findAllByImei()` originally eager-loaded the
`customer` relation the normal way, which meant `BranchScope` silently
hid the customer's details whenever the lookup was run by a user from a
*different* branch than the one on record — exactly the "customer came back
to a different branch" scenario the by-IMEI endpoint exists to handle. Fixed
by eager-loading with `withoutGlobalScopes()` for that one relation, with a
comment explaining why. Caught by
`tests/Feature/Api/V1/CustomerDeviceTest.php`.

### A testing gotcha worth knowing before you add more tests

Sanctum's auth guard caches the resolved user for the rest of the test
*process*, not just one HTTP call. Two `$this->withToken($tokenA)->...` then
`$this->withToken($tokenB)->...` calls in the *same* test function will not
reliably re-authenticate as the second user — the first user's resolution
sticks. Always split "user A does X, user B is denied X" into two separate
`it()` tests rather than chaining both calls in one. (Same root cause as the
token-revocation test note from Stage 2.)
