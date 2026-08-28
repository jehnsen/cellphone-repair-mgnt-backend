# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A pure JSON API backend (Laravel 13, PHP 8.3+) for a cellphone repair shop:
repair intake/release, chain-of-custody proof, inventory across three item
classes, point of sale, buy-back/refurb, and reporting. The full domain
design — ERDs, table catalogue, enums, ticket state machine, error code
catalogue, endpoint list, and the numbered "Rule N" constraints referenced
throughout the codebase — lives in
[`docs/design/01-domain-design.md`](docs/design/01-domain-design.md). Read
it before touching migrations, routes, or the state machine; this file only
covers how to work day-to-day.

The build follows a staged order (see design doc §8): Stage 1 design → Stage
2 API skeleton → Stage 3 schema/seeders → Stage 4 master data (branches,
users, catalog, customers/devices) → Stage 5 repair tickets/state
machine/timeline/photos/quotes → Stage 6 inventory core (suppliers,
serialized units, the stock ledger) → Stage 7 chain of custody (IMEI
checkpoints, part swaps, public verification) → Stage 8 POS (shifts, sales,
payments) → purchase orders/goods receipts, refunds, buy-back/refurb,
installments, and reporting. **Not built**: notifications/message
templates, the unclaimed-notice 30/60/90-day job, document reprints, and a
scheduled command to populate the reporting rollup tables (reports compute
live instead — see ReportService's docblock). Check `routes/api.php` and
`app/Http/Controllers/Api/V1/` to see what's actually implemented before
assuming something exists.

## Commands

```bash
composer install && cp .env.example .env && php artisan key:generate
php artisan migrate --seed        # or migrate:fresh --seed from empty
composer test                     # clears config cache, runs full Pest suite
./vendor/bin/pest tests/Feature/Api/V1/RepairTicketTest.php   # single file
./vendor/bin/pest --filter="rejects an illegal status transition"  # single test
php artisan route:list --path=v1  # confirm route registration after adding routes
./vendor/bin/pint                 # code style (no pint.json — Laravel defaults)
```

Two MariaDB databases must exist before anything works: `cp_repair_db` (dev,
seeded) and `cp_repair_db_testing` (Pest — see `phpunit.xml`, `mysql`
driver, not SQLite, because the schema uses FULLTEXT indexes and CHECK
constraints SQLite can't validate). `RefreshDatabase` in tests only ever
touches the `_testing` one.

### Local environment caveats (see README for full detail)

- This machine's MySQL is actually **MariaDB 10.4.32**, not MySQL 8 —
  schema uses `utf8mb4_unicode_ci`, not `utf8mb4_0900_ai_ci`.
- **Redis is not running locally.** `.env` falls back to
  `CACHE_STORE=database` / `QUEUE_CONNECTION=database`; `.env.example`
  still documents Redis as the real target. `GET /api/v1/ready` will
  correctly report `503` with `"redis": false` until it's started — that's
  expected, not a regression.
- Verify the Postman collection end-to-end with `npx newman run
  docs/postman/Cellphone-Repair-Shop-API.postman_collection.json` against a
  freshly seeded DB (`migrate:fresh --seed`) and a running `php artisan
  serve` — re-seed between runs or unique-constraint violations cascade into
  unrelated-looking failures.

## Architecture

### Rule Zero: JSON, always

No Blade, no sessions, no CSRF. Every response is `application/json` or
`204`, including every error status. Enforced in `bootstrap/app.php`
(`ForceJsonResponse` middleware + `shouldRenderJsonWhen(fn () => true)`).
Binary (photo uploads) is the one Rule Zero-adjacent case: it goes in as
multipart but always comes back out as JSON — a ULID plus a short-TTL
signed URL (`Storage::disk('local')->temporaryUrl()`), never raw bytes
through a controller.

### Request flow: Controller → Service → Repository

Every endpoint follows the same four-file split — a Controller stays thin
and never touches Eloquent directly:

- **Repository** (`app/Repositories/`) — the only layer that talks to
  Eloquent. `BaseRepository` gives generic CRUD (`all`, `paginate`, `find`,
  `findByUlid`, `create`, `update`, `delete`) via
  `spatie/laravel-query-builder`; concrete repositories only override
  `allowedFilters()`/`allowedSorts()`/`defaultSort()`/`filteredQuery()`
  (explicit allow-lists only — never expose an unlisted column to
  `filter[...]`) and add model-specific finders. Bound to interfaces in
  `App\Providers\RepositoryServiceProvider`.
- **Service** (`app/Services/`) — business logic, and the *only* layer
  allowed to call `->create()`/`->update()` on a model. Injected with a
  repository *interface*, never the concrete class.
- **Controller** (`app/Http/Controllers/Api/V1/`) — validates via a Form
  Request, resolves any client-supplied ULIDs to internal ids, calls the
  service, returns a Resource. `index`/`show` authorize via
  `$this->authorize()` against a Policy; `store`/`update` delegate to the
  Form Request's own `authorize()`.
- **Request + Resource** — one Store/Update Request pair and one Resource
  per model/action; Resources are the only thing ever serialized to JSON.

**Foreign keys in request bodies are ULIDs, never internal ids** — a client
sends `"branch_ulid": "01J..."`, and the controller resolves it to an
internal id via `Branch::idFromUlid()` before it reaches the service. Route
*path* parameters don't need this — Laravel's route-model-binding already
resolves e.g. `RepairTicket $ticket` from `{ticket}` via the ulid
automatically (see `getRouteKeyName()` below).

### Data layer conventions (apply to every model, no exceptions)

- **Dual keys**: `App\Models\Concerns\HasUlid` — auto-generates `ulid` on
  `creating`, sets `getRouteKeyName()` to `ulid`, and provides the static
  `idFromUlid(string $ulid): int` helper used above. Internal `id`
  (`BIGINT AUTO_INCREMENT`) is never exposed anywhere a client can see it —
  not in URLs, not in response bodies, not in request bodies.
- **Branch scoping**: models with `branch_id` carry
  `#[ScopedBy(BranchScope::class)]` (`app/Models/Scopes/BranchScope.php`) —
  scopes every query to `Auth::user()->branch_id` when authenticated, a
  no-op otherwise (console/seeders/cross-branch jobs see everything).
  **Gotcha**: a record's *related* model can legitimately sit in a
  different branch than the record itself (a repeat customer, a technician
  covering another branch) — eager-loading that relation the normal way
  silently nulls it out under `BranchScope`. Fix is `withoutGlobalScopes()`
  on that specific relation load (see
  `RepairTicketService::loadDisplayRelations()`,
  `CustomerDeviceRepository::findAllByImei()`, and
  `StockAdjustmentRepository::filteredQuery()` (its `creator` is a
  branch-scoped `User`) for places this already bit or was pre-empted.
- **A `#[Fillable]` list missing a genuinely-required column fails
  silently, not loudly** — `Sale` was missing `sale_number`; mass-assigned
  `Sale::create([...])` just dropped it (Eloquent guards unlisted keys out
  of `create()`/`fill()` without error) and the DB's `NOT NULL` constraint
  is what actually caught it. Invisible until Stage 8, because
  `ShiftAndSalesSeeder` only ever used `Sale::factory()->create()` —
  factories set attributes directly and bypass `$fillable` entirely. A
  model exercised only through its factory can hide a broken `Fillable`
  array indefinitely; the first real mass-assignment write is what proves it.
- **Append-only ledgers** (`ticket_events`, `stock_movements`, `payments`,
  `commission_entries`, ...): `const UPDATED_AT = null`, only ever
  `create()`, never `update()`. Timeline/ledger endpoints use
  `cursorPaginate()`, not `paginate()`.
- **Money** is `decimal:2` cast everywhere — comparing against a raw PHP
  number in a test will fail (`'1000.00' !== 1000`); assert against the
  string.
- **CHECK constraints** (cost/price non-negative, a serialized unit needing
  an IMEI or serial, a ticket line matching its `line_type` via
  product_id-xor-service_id) are raw `DB::statement` in migrations, since
  Laravel's schema builder has no first-class CHECK support — and are
  re-validated at the Form Request layer too, since MariaDB CHECK errors
  aren't pretty JSON.
- **Gapless document numbers**: `App\Models\Sequence::next()` does a
  `SELECT ... FOR UPDATE` counter per branch/scope/year/month; ticket/sale
  numbers embed the branch's `code` (`JO-QC-202608-0001`) because the
  counter itself is per-branch.

### Errors and the state machine

- `App\Support\Api\ErrorCode` (backed enum) + `ApiException` +
  `ApiResponse` — every thrown business error maps to a cataloged HTTP
  status via `ErrorCode::defaultStatus()`. Check this mapping before
  asserting a status in a test: state-conflict-style errors
  (`InvalidStatusTransition`, `UnitAlreadySold`, `IdempotencyConflict`, ...)
  are **409**, not 422 — 422 is reserved for `ValidationFailed` and a
  handful of other genuinely-a-validation-rule codes.
- `App\Support\TicketStateMachine` is the single source of truth for legal
  repair-ticket transitions (fixed graph, not configurable) —
  `assertCanTransition()` throws `InvalidStatusTransition` with the allowed
  set in `error.details`. The graph itself doesn't encode the release guard
  though — it lives in `RepairTicketService::transition()`: a settled
  balance (`assertBalanceSettledForRelease()`, throws `PaymentSumMismatch`
  — reused rather than adding a new code, since an unpaid balance *is* a
  payment-sum mismatch). A release-phase-IMEI-verification guard
  (`assertImeiClearedForRelease()`) was also built and then deliberately
  removed — it blocked real releases whenever staff hadn't run a
  release-phase scan (or a seeded device's IMEI never passed Luhn to begin
  with); IMEI verification stays chain-of-custody documentation, not a
  release gate. See Chain of custody below.
- Idempotency: every write honors an optional `Idempotency-Key` header
  (`App\Http\Middleware\EnsureIdempotencyKey`) — same key + same body
  replays the cached response; same key + different body is `409
  IDEMPOTENCY_CONFLICT`.

### Inventory ledger

`App\Services\StockMovementRecorder::record()` is the one place allowed to
write `stock_movements` (append-only, source of truth) and update
`stock_levels` (a cached, never-authoritative balance) — same
lock-then-get-or-create-then-retry-on-race shape as `Sequence::next()`.
Every stock-moving action calls this: serialized-unit registration and
acquisition completion (`+1 receipt`), write-offs (`-1 write_off`), stock
adjustments (signed, per line), goods receipts and PO receiving
(`+qty receipt`), sales/refunds/voids (`sale`/`return_in`), and refurb job
lines (`refurb_consumption` — added via migration since `ticket_consumption`
is specifically a repair-ticket concept and a refurb job isn't tied to a
customer ticket; mislabeling it would have been worse than widening the
enum). `reference_id` on a movement is an internal `BIGINT` and is
deliberately not serialized (no `morphMap` yet to turn it back into the
referenced row's ulid) — only `reference_type` (a plain string like
`"stock_adjustment"`) ships. Ticket-line part consumption
(`movement_type=ticket_consumption`) is wired: `RepairTicketService::addLine()`
checks availability and records the movement for a `part` line whose
product has `track_inventory=true`, in the same transaction as the
`TicketLine` row, and stamps `ticket_lines.stock_movement_id` (a column
that had sat unused since the Stage 5 migration, anticipating exactly
this) — `labor` lines never touch stock, guaranteed by the CHECK
constraint that a `labor` line has no `product_id`. There's still no
reversal path if a line is ever removed (no `destroy` route exists for
ticket lines at all yet, so nothing currently un-consumes it).

### Chain of custody

IMEI checkpoints (`imei_verifications`, no ulid — nested-only) record every
scan regardless of match; a mismatch is logged, never rejected — and, since
the release guard was removed, unresolved verification state (missing,
mismatched, or never overridden) no longer blocks releasing a ticket
either. It stays a documentation/proof layer only. The owner escape hatch
(`.../imei-verifications/override`, `tickets.imei_override`)
is a *separate* verification row with its own `override_reason` and
`overridden_by`, not an edit to a past one — there's no ulid to target one
by design. Part swaps (`part_swaps`) are a documentation-only record of
what physically came out/went in; they never touch the inventory ledger
(that's still `ticket_lines`, deferred). `GET /public/verify/{token}` is
the one unauthenticated route in the API — gated by the `public-verify`
rate limiter (`AppServiceProvider`), not auth — and is deliberately
redacted (`PublicVerificationResource`: no customer PII, no `claim_code`,
no pricing). Its `App\Models\VerificationToken` is created alongside every
ticket but only surfaced to staff via `RepairTicketResource.verification_token`
(gated by `tickets.view`) — without that field the endpoint has no way to
be reached from the authenticated API at all.

### POS

`App\Support\SaleCalculator` (pure, unit-testable) does the VAT/discount
math: VAT-inclusive pricing, `vat = gross / 1.12 * 0.12`; at most one
discount per line plus one for the whole sale; `senior_citizen`/`pwd` is
sale-scope only and converts that portion to VAT-exempt *and* takes a
(legally-fixed, defaults to 20% if the client omits `value`) cut off the
VAT-exclusive base. `App\Services\PaymentRecorder` is shared between sales
and repair-ticket payments — append-only, cash tendered/change, rejects
overpayment (`PaymentSumMismatch`). A sale requires the cashier's own open
shift (`ShiftRepositoryInterface::findOpenFor()`, resolved server-side —
never a request field); a repair ticket's balance is paid *directly*
(`POST /tickets/{ticket}/payments`, `payable_type=repair_ticket`), never
wrapped in a Sale — the schema's `sale_lines.sellable_type=ticket_balance`
has no column linking a line back to a specific ticket, so it's unused.
`ShiftService::close()` reconciles *cash-only* payments (`Payment.shift_id`
+ `method=cash`) plus cash movements against the counted drawer — gcash/
card never touch `expected_cash`.

### Purchase orders, refunds, buy-back/refurb, installments

Two more instances of "no ulid on this nested table, so how does the client
reference one row": `ReceivePurchaseOrderRequest` keys purchase-order lines
by `product_ulid` (a PO won't realistically repeat the same product across
lines); `StoreRefundRequest` keys sale lines by their *position* in
`GET /sales/{sale}`'s `data.lines` array (`line_index`), since a sale
legitimately can repeat a product. Never send an internal id back to the
client to solve this — pick whichever of these two patterns actually fits.

`installment_schedules` needed a real `ulid` column added via migration —
the design doc calls it "no ulid" but its own endpoint list then routes a
specific schedule by `{scheduleId}`, which would otherwise be the one place
in the whole API exposing an internal `BIGINT` in a URL.

`Acquisition` has no `product_id` — the exact product/model is identified
at `complete()` time (after physical inspection), which is when
`SerializedUnitService::create()` (Stage 6) actually gets reused to make
the real unit; a buy-back completion *is* a receipt. The brief's own guard
— never complete while `imei_check_result=flagged`
(`AcquisitionImeiFlagged`) — lives in the service, not a DB `CHECK`, since
it depends on another column's value at a point in time, not the row
itself. A `RefurbJob` moves that unit to `for_repair` while parts go in,
then back to `in_stock` on completion with `acquisition_cost` updated to
the job's final `landed_cost` (parts + labor + the original acquisition
price) — the unit's new true cost basis.

Paying an installment schedule also writes a real `Payment` against the
*sale* (via the same `PaymentRecorder` sales and ticket payments use) —
there's no parallel bookkeeping system for installments.

`App\Services\ReportService` computes all 9 `/reports/*` endpoints live
from transactional tables — the design brief wants rollup tables instead
(`daily_metrics` etc., already migrated in Stage 3), but nothing populates
them yet. Don't assume those rollup tables have data in them.

### Resource-serialization gotcha

`new SomeResource($this->whenLoaded('nullableRelation'))` is safe **only**
through the normal `->resolve()`/`->response()` pipeline — Laravel's
`JsonResource::filter()` specifically turns a nested `new XResource(null)`
into `null` before serialization. That safety net is skipped if you ever
call `->toArray($request)` directly and hand-roll the result into
`response()->json([...])` (as a multi-resource response like "quote plus
the ticket it belongs to" is tempted to do) — use `->resolve($request)`
instead in that situation, or you'll get a null-property crash the moment a
nullable relation (an unassigned technician, say) is actually null.

## Testing

Pest, against the real `cp_repair_db_testing` MariaDB database. Helpers in
`tests/Pest.php` include `userWithRole(string $role, ?Branch $branch)` —
seeds roles/permissions (`RoleAndPermissionSeeder`), creates a user with
that role, returns `[$user, $token]`.

**Sanctum gotcha**: the auth guard caches the resolved user for the rest of
the test *process*, not just one HTTP call. Two
`$this->withToken($tokenA)->...` then `$this->withToken($tokenB)->...`
calls in the *same* `it()` will not reliably re-authenticate as the second
user. Split "actor A can, actor B can't" into two separate tests, or seed
the second actor's data directly via factories instead of through an HTTP
call as a different token.

## Postman collection

[`docs/postman/Cellphone-Repair-Shop-API.postman_collection.json`](docs/postman/Cellphone-Repair-Shop-API.postman_collection.json)
covers every implemented endpoint, grouped into folders that mirror the
build stages, with a `Cleanup (run last)` folder for hard/soft deletes. Every
Create request auto-captures its new `ulid` into a collection variable
consumed by the Show/Update/nested requests below it. One convention to
preserve when extending it: use `{{owner_branch_ulid}}` (the seeded owner's
*own* branch — set by "Branches → Find My Branch"), not `{{branch_ulid}}`
(a freshly-created branch), for anything the same authenticated actor needs
to read back afterward — `BranchScope` otherwise hides it, same as the
eager-loading gotcha above.
