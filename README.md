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
- Inventory: supplier CRUD and soft-delete, serialized-unit registration
  posting a receipt movement, the non-serialized-product and
  imei-or-serial-number validation guards, `sold` rejected on the plain
  update endpoint, a write-off posting its `-1` movement, stock adjustments
  keeping the ledger and the cached level in sync, the zero-delta and
  serialized-unit-not-±1 validation guards, and the `inventory.adjust` vs
  `inventory.view` permission split (`SupplierTest.php`,
  `SerializedUnitTest.php`, `StockAdjustmentTest.php`, `InventoryTest.php`).
- Chain of custody: matching and mismatched IMEI scans both recording
  (never rejecting) correctly, invalid-IMEI validation, the
  `tickets.imei_override` permission gate, the release transition blocked
  with `409 IMEI_MISMATCH` until a matching (or overridden) release-phase
  verification exists, part-swap recording and its removed-photo-belongs-
  to-this-ticket guard, and the full loop from ticket creation through the
  exposed `verification_token` to a successful unauthenticated
  `GET /public/verify/{token}`, including 404s for unknown/revoked tokens
  (`ImeiVerificationTest.php`, `PartSwapTest.php`,
  `PublicVerificationTest.php`).
- POS: opening/closing a shift (including the reconcile-cash-only-not-gcash
  behavior and the double-open/double-close guards), a cashier blocked from
  closing another's shift, cash movements against open vs. closed shifts,
  sale creation for all three sellable types (product stock consumption,
  serialized-unit status flip and its double-sell guard, service with no
  stock effect), line and sale-scope discounts (including the senior-citizen
  VAT-exempt-plus-20%-off computation), the insufficient-stock and
  no-open-shift guards, voiding a sale and confirming stock is restored,
  split payments against a sale and the overpayment guard, and — tying
  Stages 5/7/8 together — a ticket payment reducing balance, its own
  overpayment guard, and the full release guard chain (IMEI cleared but
  balance unpaid still blocks release; paying it off then unblocks it)
  (`ShiftTest.php`, `SaleTest.php`, `TicketPaymentTest.php`,
  `DiscountCalculatorTest.php`).
- Purchase orders/goods receipts/refunds/buy-back/installments/reporting:
  submit-then-partially-then-fully receive a PO with the status sync at
  each step, rejecting a receive on a draft PO and over-receiving a line,
  ad-hoc goods receipts, a restocking refund (and confirming a `write_off`
  one does *not* restock), refunding more than a line has left and an
  out-of-range `line_index`, the `sales.refund` permission gate, an
  acquisition blocked from completing while IMEI-flagged and rejected on a
  second completion, a refurb job moving its unit to `for_repair` then
  back with the recomputed `landed_cost` becoming the unit's new
  `acquisition_cost`, an installment plan's even split (with the rounding
  remainder landing on the last row), paying a schedule and rejecting a
  second payment once it's already `paid`, and the `reports.view` /
  `reports.margin.view` permission gates per report (`PurchaseOrderTest.php`,
  `RefundTest.php`, `AcquisitionTest.php`, `RefurbJobTest.php`,
  `InstallmentPlanTest.php`, `ReportTest.php`).

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
photos, and quotes; Stage 6 adds the inventory core — suppliers, serialized
units, and the stock ledger; Stage 7 adds chain of custody — IMEI
checkpoints, part swaps, and the one public (unauthenticated) endpoint in
the whole API; Stage 8 adds POS — shifts, sales with VAT/discount
computation, and payments; a final pass rounds out the rest of the domain:
purchase orders/goods receipts, refunds, buy-back/refurb, installments,
and reporting (all below). Every one of these routes, plus the Stage 2
auth/health routes, is listed by `php artisan route:list`. What's *not*
built: notifications/message templates, the unclaimed-notice 30/60/90 job,
document reprints, and populating the reporting rollup tables on a
schedule — see `docs/design/01-domain-design.md` §8 for the full build order.

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

## Inventory: suppliers, serialized units, the stock ledger (Stage 6)

- **The ledger** (`App\Services\StockMovementRecorder`) — the one place
  allowed to write `stock_movements` (append-only, the source of truth) and
  keep `stock_levels` (a cached, never-authoritative balance) in sync.
  Generalizes the pattern `InventorySeeder::postReceipt()` established for
  the demo data: lock the `stock_levels` row (`SELECT ... FOR UPDATE`,
  get-or-create with the same create/race-retry shape as `Sequence::next()`),
  compute the new balance, append the movement, update the cache — all
  inside the caller's transaction. Every future stock-moving action (goods
  receipts, sales, ticket-line consumption, transfers) should call this
  rather than touch either table directly.
- **Suppliers** (`/suppliers`) — plain CRUD, soft-deletable. Read is gated
  by `inventory.view` (every role has it); write is gated by a new
  `suppliers.manage` permission (owner/manager only) rather than
  `inventory.adjust`, since managing a supplier's profile isn't itself a
  stock movement.
- **Serialized units** (`/serialized-units`, no `destroy`) — one row per
  IMEI/serial-tracked handset. Registering one (`inventory.receive`) is
  treated as an ad-hoc receipt: it posts a `+1` `receipt` movement via the
  recorder. A `status` change on update goes through
  `SerializedUnit::transitionStatus()` (Rule 4: a unit can only be sold
  once — compare-and-swap under a row lock); flipping to `written_off`
  additionally posts a `-1` `write_off` movement so `stock_levels` doesn't
  drift. `sold` is rejected by the Form Request on purpose — that status
  change belongs to the sales flow (Stage 8), which will post its own
  `sale`-referenced movement instead of this generic `PATCH`.
- **Stock adjustments** (`GET|POST /stock-adjustments`, no update/delete —
  a bad adjustment is corrected with an opposite one, not edited) — the
  write path exercised for now while purchase orders and goods receipts are
  still pending. Each line posts its own movement
  (`reference_type: 'stock_adjustment'`); a line naming a
  `serialized_unit_ulid` must move by exactly `±1`, enforced in the Form
  Request's `withValidator()` since it's a same-line, cross-field
  constraint `required_if`/`prohibited_if` can't express.
- **Inventory reads** (`GET /inventory/levels`, `GET /inventory/movements`)
  — the cache and the ledger, respectively; movements are cursor-paginated
  same as the ticket timeline. `reference_id` (an internal `BIGINT`) is
  never serialized — only the human-readable `reference_type` string ships,
  since there's no `morphMap` yet to resolve it back to the referenced
  row's own ulid.

**Deliberately deferred to a later pass**: purchase orders and goods
receipts (the *formal* restocking flow — `serialized-units` and
`stock-adjustments` above are the stand-ins until then), and wiring real
part-line stock consumption into `RepairTicketService::addLine()` (flagged
back in Stage 5, still open).

**A third instance of the branch-scoped-relation bug** (see the two above):
`StockAdjustment.creator` is a `User`, which is branch-scoped — whoever
posted the adjustment won't always share the viewer's branch. Applied the
same `withoutGlobalScopes()` treatment up front this time, in
`StockAdjustmentRepository::filteredQuery()` and everywhere else that
relation loads, rather than waiting to find it broken.

## Chain of custody: IMEI checkpoints, part swaps, public proof (Stage 7)

- **IMEI verifications** (`GET|POST /tickets/{ticket}/imei-verifications`,
  no `destroy`/`update` — it's an append-only checkpoint log, no ulid of
  its own per the design doc) — a scan at any of the four phases
  (`intake`/`pre_repair`/`post_repair`/`release`) is always recorded,
  match or not; a mismatch doesn't reject the request, it's just logged
  with `matches_expected: false` for the release guard (next) to act on.
- **The release guard, finally wired**: `ready_for_pickup`/`unclaimed` →
  `released` now requires a `release`-phase verification that matches (or
  an override) — `RepairTicketService::assertImeiClearedForRelease()`
  throws `IMEI_MISMATCH` (`409`) otherwise. This closes the guard
  `TicketStateMachine`'s docblock flagged as open since Stage 5; the
  balance-settlement half (Stage 8 / POS) is still pending.
- **The override endpoint** (`POST .../imei-verifications/override`,
  `tickets.imei_override`, owner/manager only) is a second, independent
  verification row carrying its own `override_reason` and
  `overridden_by` — not an edit to a past one, since the table
  deliberately has no ulid to target. Self-contained and fully logged
  either way.
- **Part swaps** (`GET|POST /tickets/{ticket}/part-swaps`) — a
  documentation-only record of what physically came out and went in,
  distinct from `ticket_lines` (the billing line for the same part).
  `removed_photo_ulid` references an existing `TicketPhoto` on the *same*
  ticket (checked in the Form Request's `withValidator()`, since
  `Rule::exists` alone can't scope to the route's ticket); the response's
  `removed_photo_url` is a controller-set virtual attribute (signed URL),
  same pattern as `TicketPhotoResource::url`.
- **Public verification** (`GET /public/verify/{token}`) — the one
  unauthenticated endpoint in the whole API, rate-limited instead of
  gated by auth (`public-verify`, 10/min/IP, registered back in Stage 2's
  `AppServiceProvider` in anticipation of this). Strictly redacted: no
  customer name/contact, no `claim_code` (that's the pickup credential,
  not a public proof), no unlock info, no pricing, no technician identity.
  `App\Models\VerificationToken` (created alongside every ticket since
  Stage 5, but never surfaced until now) backs it — a random 32-char
  token, not the ticket's own ulid, so revoking a customer's proof link
  doesn't have to touch the ticket record itself.

**A closed loop**: the verification token existed since Stage 5 but had no
consumer and wasn't exposed anywhere, so this stage also added
`RepairTicketResource.verification_token` (staff-only, via
`tickets.view`) — without it, `GET /public/verify/{token}` was reachable
in principle but not in practice, since nothing in the authenticated API
could ever produce a token to try it with.

## POS: shifts, sales, payments (Stage 8)

- **Shifts** (`POST /shifts/open`, `POST /shifts/{shift}/close`,
  `GET /shifts`/`{shift}`, `POST /shifts/{shift}/cash-movements`) — the
  branch and cashier are always the authenticated actor's own, never a
  request field. `close()` computes `expected_cash` from *cash-only*
  payments recorded during the shift (`Payment.shift_id`, `method=cash` —
  gcash/card/etc. never touch the drawer) plus cash movements in/out,
  compared against the counted amount for `variance`. A cashier closes
  their own shift; owner/manager can close on anyone's behalf
  (`ShiftPolicy::close()`). Reopening while one is already open, or
  closing/adding a cash movement to an already-closed one, are both
  rejected (422 and 409 `SHIFT_NOT_OPEN` respectively — deliberately
  different codes: the first is a request-validation concern the client
  controls, the second is a state conflict on the resource itself).
- **Sales** (`GET|POST /sales`, `GET /sales/{sale}`,
  `POST /sales/{sale}/void`, `POST /sales/{sale}/payments`) — checkout
  requires an open shift (`409 SHIFT_NOT_OPEN` otherwise) and supports
  three sellable types: `product` (checks and consumes stock via
  `StockMovementRecorder`, `422 INSUFFICIENT_STOCK` if short),
  `serialized_unit` (flips the unit `in_stock → sold` through its existing
  `transitionStatus()` guard — `409 UNIT_ALREADY_SOLD` on a repeat sale),
  and `service` (no stock effect). `sale_lines.sellable_type=ticket_balance`
  from the schema is **not** implemented — see below. `void()` reverses
  both: a `return_in` stock movement for products, and the serialized
  unit's status back to `in_stock`; a full refund flow with per-line
  restocking choices is a separate, not-yet-built action.
- **VAT and discounts** (`App\Support\SaleCalculator`, unit-testable
  without a database) — VAT-inclusive pricing, `vat = gross / 1.12 * 0.12`
  (the same formula `ShiftAndSalesSeeder` already used for demo data,
  generalized to handle discounts). At most one discount per line
  (percent/amount) plus one for the whole sale; `senior_citizen`/`pwd` is
  sale-scope only — legally it's the customer's whole transaction that's
  exempt, not individual items — and converts that portion to VAT-exempt
  *and* takes 20% off the VAT-exclusive base, matching how PH retail OCR
  receipts show it. The 20% rate applies even if the client omits `value`
  for that discount type (it's legally fixed, not something the cashier
  sets). `zero_rated_sales` is always 0 — nothing this shop sells
  qualifies for zero-rating. A non-VAT-registered branch (`Branch.vat_registered`)
  still fills `vatable_sales` but `vat_amount` is always 0.
  `GET /discounts/calculate` runs the exact same calculator statelessly,
  for a POS UI to preview a discount before checkout commits to it.
- **Payments** (`App\Services\PaymentRecorder`, shared by sales and
  tickets) — append-only, handles cash tendered/change, and rejects
  overpayment past what's actually owed (`409 PAYMENT_SUM_MISMATCH`,
  ~1-centavo rounding tolerance). A sale supports split payments across
  methods via repeated calls to the same endpoint (part cash, part gcash);
  there's no `sale.balance_due` column, so "how much is left" is always
  `total − sum(payments)` computed on demand.
- **A repair ticket's balance is paid directly**
  (`POST /tickets/{ticket}/payments`, `payable_type=repair_ticket`), never
  through a Sale wrapper — the schema's `sale_lines.sellable_type=ticket_balance`
  has no column linking a line back to a specific ticket, so this endpoint
  is the actual path, not that one. `RepairTicketService::recalculateBalance()`
  now subtracts `SUM(payments)` in addition to the intake-time
  `downpayment` column (which stays separate rather than becoming a
  retroactive Payment row, since it predates POS/shifts existing at
  intake). **This closes the second and last release guard** flagged open
  since Stage 5: `ready_for_pickup`/`unclaimed` → `released` now also
  requires `balance <= 0`
  (`RepairTicketService::assertBalanceSettledForRelease()`, reusing
  `PaymentSumMismatch` since an unpaid balance *is* a payment-sum mismatch)
  — the IMEI half was closed in Stage 7, and this was the only guard
  `TicketStateMachine`'s docblock still listed as open.

**A real bug this surfaced**: `Sale`'s `#[Fillable]` attribute list was
missing `sale_number` — invisible until now because `ShiftAndSalesSeeder`
only ever used `Sale::factory()->create()`, and Eloquent factories fill
attributes directly, bypassing mass-assignment guarding entirely. The
first real `Sale::create([...])` call (`SaleService`) hit a `NOT NULL`
database error instead, since the column silently dropped out of the
insert. Fixed by adding it to the model's `Fillable` list. A reminder that
factory-only coverage of a model can hide a broken `Fillable` array
indefinitely.

**Deliberately not implemented**: the `ticket_balance` sellable type
(superseded by the direct ticket-payment endpoint above) — refunds
themselves are built below.

## Purchase orders, goods receipts, refunds, buy-back/refurb, installments, reporting

The remaining bounded contexts from the design doc, in one pass:

- **Purchase orders + goods receipts** (`GET|POST /purchase-orders`,
  `PATCH /purchase-orders/{ulid}`, `POST .../receive`,
  `GET|POST /goods-receipts`) — the *formal* restocking flow Stage 6
  deferred. A PO moves `draft → submitted → cancelled`, or gets received
  (once or partially, any number of times) into `partially_received` /
  `received`, computed from every line's `received_qty` vs `ordered_qty`.
  `GoodsReceiptService::post()` is the one place that actually posts the
  stock movement — called both by an ad-hoc receipt (no PO) and by
  `PurchaseOrderService::receive()`. Receiving lines are keyed by
  `product_ulid`, not an internal `purchase_order_line` id — that table
  has no ulid of its own, and Rule 6 rules out sending the internal id
  back just to round-trip it (see `ReceivePurchaseOrderRequest`'s
  docblock). Serialized units still register via the Stage 6
  `POST /serialized-units` endpoint, not a receipt line.
- **Refunds** (`POST /sales/{sale}/refunds`) — same "no ulid to reference
  a line by" problem as above, solved the same way but by position instead
  of product: a line is keyed by its index into `GET /sales/{sale}`'s
  `data.lines` array. `restock_behavior=restock` reverses the sale's stock
  effect (a `return_in` movement, or a serialized unit flipped back to
  `in_stock`); `no_restock`/`write_off` both leave stock exactly as the
  original sale left it — the only question restock_behavior answers is
  whether the item can go back on the shelf, not whether it "un-happens".
  The sale's status becomes `partially_refunded` or `refunded` once every
  line is fully covered.
- **Buy-back / refurb** (`GET|POST /acquisitions`,
  `POST .../imei-check`, `POST .../complete`, `GET|POST /refurb-jobs`,
  `POST .../lines`, `POST .../complete`) — `Acquisition` has no
  `product_id` of its own; the shop only knows the seller/IMEI/offered
  price at intake, and identifies the exact product/model at `complete()`
  time (after physical inspection), which is when the real
  `SerializedUnit` actually gets created (reusing `SerializedUnitService`
  from Stage 6 — an acquisition completion *is* a receipt). The design
  brief's own guard — never complete while `imei_check_result=flagged`
  (`409 ACQUISITION_IMEI_FLAGGED`) — is enforced in the service, not a DB
  `CHECK`, since it depends on another column's value at a point in time.
  A refurb job takes that unit out of sellable circulation (`for_repair`)
  while parts go into it — each line posts a stock movement (see the
  `refurb_consumption` movement type below) and recomputes `landed_cost`
  (parts + labor + the original acquisition price) — then back to
  `in_stock` on completion, with the unit's `acquisition_cost` updated to
  the final `landed_cost` as its new true cost basis.
- **Installments** (`GET|POST /installment-plans`,
  `POST .../schedules/{schedule}/pay`) — creating a plan splits
  `sale.total − downpayment` evenly across `term_months` (the last
  instalment absorbs the rounding remainder so the schedule always sums
  back exactly). Paying a schedule also records a real `Payment` against
  the *underlying sale* (`payable_type=sale`) through the same
  `PaymentRecorder` sales and ticket payments use — one unified payments
  ledger, not a parallel one for instalments. `installment_schedules`
  needed a `ulid` added via migration: the design doc calls it "no ulid"
  but then routes a specific schedule by `{scheduleId}` in its own
  endpoint list — the one place in the whole design that would otherwise
  put an internal `BIGINT` in a URL (Rule 6). Added the column instead of
  accepting that contradiction.
- **Reports** (`GET /reports/sales`, `/margin`, `/technician-throughput`,
  `/most-repaired-models`, `/warranty-failure-rate`, `/inventory-valuation`,
  `/dead-stock`, `/unclaimed-aging`, `/commissions-payable`) — all
  read-only, JSON with `data.aggregate`/`data.rows` and
  `meta.generated_at`. The design brief's own rule says these should read
  from rollup tables (`daily_metrics` etc. — they exist, Stage 3), never
  scan transactional tables per request; nothing populates those rollups
  yet (that's a scheduled command, its own undertaking), so
  `ReportService` computes live instead. Fine at this shop's data volume;
  switching the read side later shouldn't change any method's signature.
  `/margin` and `/inventory-valuation` require `reports.margin.view`
  in addition to `reports.view` (403 for a cashier), same gate as
  `ProductResource.cost`.

**Two real bugs this pass surfaced:**

- A JOIN across `ticket_events` and `repair_tickets` in the technician-
  throughput report referenced a bare `created_at` — both tables have one,
  and MariaDB rejected the query outright (`Column 'created_at' in where
  clause is ambiguous`) rather than silently picking one. Fixed by
  qualifying it as `ticket_events.created_at`. A reminder that an unqualified
  column name is only safe until the next join touches a same-named column.
- `acquisitions.seller_id_photo_ref` was `NOT NULL` in the schema with no
  way to satisfy it — there's no photo-upload endpoint for acquisitions
  (deliberately out of scope; it's a plain string reference for now, not a
  Rule Zero ULID+signed-URL pair like `ticket_photos`), so every
  `POST /acquisitions` failed outright. Fixed with a migration making the
  column nullable.

`stock_movements.movement_type` also gained a `refurb_consumption` value
via migration — `ticket_consumption` is specifically a repair-ticket
concept, and a refurb job isn't tied to a customer ticket, so reusing it
would have mislabeled the ledger rather than just being imprecise.

**Still not implemented** (see design doc §8): notifications/message
templates, the unclaimed-notice 30/60/90-day workflow, document reprints,
and a scheduled command to actually populate the reporting rollup tables.

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
