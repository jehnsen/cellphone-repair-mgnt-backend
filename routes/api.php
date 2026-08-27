<?php

use App\Http\Controllers\Api\V1\AcquisitionController;
use App\Http\Controllers\Api\V1\Auth\TokenController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\Catalog\DeviceBrandController;
use App\Http\Controllers\Api\V1\Catalog\DeviceModelController;
use App\Http\Controllers\Api\V1\Catalog\ProductCategoryController;
use App\Http\Controllers\Api\V1\Catalog\ProductController;
use App\Http\Controllers\Api\V1\Catalog\ServiceController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerDeviceController;
use App\Http\Controllers\Api\V1\DiscountController;
use App\Http\Controllers\Api\V1\GoodsReceiptController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ImeiVerificationController;
use App\Http\Controllers\Api\V1\InstallmentPlanController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Api\V1\PartSwapController;
use App\Http\Controllers\Api\V1\PublicVerificationController;
use App\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Http\Controllers\Api\V1\RefurbJobController;
use App\Http\Controllers\Api\V1\RepairFindingController;
use App\Http\Controllers\Api\V1\RepairTicketController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\SerializedUnitController;
use App\Http\Controllers\Api\V1\ShiftController;
use App\Http\Controllers\Api\V1\StockAdjustmentController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\TicketLineController;
use App\Http\Controllers\Api\V1\TicketPaymentController;
use App\Http\Controllers\Api\V1\TicketPhotoController;
use App\Http\Controllers\Api\V1\TicketQuoteController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

// Everything lives under /api/v1 (see docs/design/01-domain-design.md §6).
// This file grows context-by-context; Stage 2 wired the skeleton
// (health/readiness, Sanctum token issuance). Stage 4 adds master data.
Route::prefix('v1')->group(function (): void {
    Route::get('/health', [HealthController::class, 'health']);
    Route::get('/ready', [HealthController::class, 'ready']);

    Route::post('/auth/token', [TokenController::class, 'store']);

    // The one unauthenticated endpoint in the API — chain-of-custody proof,
    // not a repair-management action. Its own strict limiter (10/min/IP,
    // see AppServiceProvider), not auth, is what keeps it from being scraped.
    Route::middleware('throttle:public-verify')->get('/public/verify/{token}', [PublicVerificationController::class, 'show']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/tokens', [TokenController::class, 'index']);
        Route::delete('/auth/tokens/{tokenId}', [TokenController::class, 'destroy'])
            ->whereNumber('tokenId');
        Route::post('/auth/logout', [TokenController::class, 'destroyCurrent']);

        // Controlled vocabularies (repair-findings root_cause / defects /
        // resolution) so the frontend never keeps a second hardcoded copy.
        Route::get('/meta/enums', [MetaController::class, 'enums']);

        // Identity & shop — no destroy: branches deactivate via update(),
        // they don't get removed (see docs/design/01-domain-design.md Flag 1).
        Route::apiResource('branches', BranchController::class)->except(['destroy']);
        Route::apiResource('users', UserController::class);

        // Catalog
        Route::apiResource('device-brands', DeviceBrandController::class);
        Route::apiResource('device-models', DeviceModelController::class);
        Route::apiResource('services', ServiceController::class);
        Route::apiResource('product-categories', ProductCategoryController::class);
        Route::apiResource('products', ProductController::class);

        // Customers & devices
        Route::apiResource('customers', CustomerController::class);
        Route::apiResource('customers.devices', CustomerDeviceController::class)
            ->parameters(['devices' => 'device']);
        Route::get('/devices/by-imei/{imei}', [CustomerDeviceController::class, 'historyByImei']);

        // Repair tickets — Stage 5: state machine, timeline, photos, quotes.
        Route::apiResource('tickets', RepairTicketController::class)
            ->parameters(['tickets' => 'ticket'])
            ->except(['destroy']);
        Route::post('/tickets/{ticket}/transition', [RepairTicketController::class, 'transition']);
        Route::get('/tickets/{ticket}/events', [RepairTicketController::class, 'events']);

        // Findings & root cause — one record per ticket, PUT is an upsert.
        // A released ticket's record is closed (409); corrections go on the
        // timeline, which is also where every edit is audited
        // (finding_recorded events). See RepairFindingService.
        Route::get('/tickets/{ticket}/finding', [RepairFindingController::class, 'show']);
        Route::put('/tickets/{ticket}/finding', [RepairFindingController::class, 'upsert']);

        Route::get('/tickets/{ticket}/lines', [TicketLineController::class, 'index']);
        Route::post('/tickets/{ticket}/lines', [TicketLineController::class, 'store']);

        Route::get('/tickets/{ticket}/photos', [TicketPhotoController::class, 'index']);
        Route::post('/tickets/{ticket}/photos', [TicketPhotoController::class, 'store']);

        Route::get('/tickets/{ticket}/quotes', [TicketQuoteController::class, 'index']);
        Route::post('/tickets/{ticket}/quotes', [TicketQuoteController::class, 'store']);
        Route::post('/tickets/{ticket}/quotes/{quote}/respond', [TicketQuoteController::class, 'respond']);

        // Chain of custody — IMEI checkpoints and part swaps. release now
        // requires a matching release-phase verification (or an owner
        // override) — see RepairTicketService::assertImeiClearedForRelease().
        Route::get('/tickets/{ticket}/imei-verifications', [ImeiVerificationController::class, 'index']);
        Route::post('/tickets/{ticket}/imei-verifications', [ImeiVerificationController::class, 'store']);
        Route::post('/tickets/{ticket}/imei-verifications/override', [ImeiVerificationController::class, 'override']);

        Route::get('/tickets/{ticket}/part-swaps', [PartSwapController::class, 'index']);
        Route::post('/tickets/{ticket}/part-swaps', [PartSwapController::class, 'store']);

        // A ticket's balance is paid directly, not through a Sale wrapper —
        // see TicketPaymentController's docblock. This is also what the
        // released release guard now requires be settled.
        Route::get('/tickets/{ticket}/payments', [TicketPaymentController::class, 'index']);
        Route::post('/tickets/{ticket}/payments', [TicketPaymentController::class, 'store']);

        // Inventory — Stage 6: suppliers, serialized units, the stock
        // ledger (stock_movements is the source of truth; stock_levels is
        // a derived cache — see StockMovementRecorder). stock_adjustments
        // remains a write path alongside the purchase-order/goods-receipt
        // flow below.
        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('serialized-units', SerializedUnitController::class)
            ->except(['destroy']);

        Route::get('/inventory/levels', [InventoryController::class, 'levels']);
        Route::get('/inventory/movements', [InventoryController::class, 'movements']);

        Route::get('/stock-adjustments', [StockAdjustmentController::class, 'index']);
        Route::post('/stock-adjustments', [StockAdjustmentController::class, 'store']);
        Route::get('/stock-adjustments/{stockAdjustment}', [StockAdjustmentController::class, 'show']);

        // Purchase orders + goods receipts — the formal restocking flow
        // (see GoodsReceiptService's docblock; serialized units still
        // register via POST /serialized-units, not a receipt line).
        Route::apiResource('purchase-orders', PurchaseOrderController::class)
            ->except(['destroy']);
        Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive']);

        Route::get('/goods-receipts', [GoodsReceiptController::class, 'index']);
        Route::post('/goods-receipts', [GoodsReceiptController::class, 'store']);
        Route::get('/goods-receipts/{goodsReceipt}', [GoodsReceiptController::class, 'show']);

        // POS — Stage 8: shifts, sales (VAT + discounts, see SaleCalculator),
        // payments, refunds.
        Route::get('/shifts', [ShiftController::class, 'index']);
        Route::post('/shifts/open', [ShiftController::class, 'open']);
        Route::get('/shifts/{shift}', [ShiftController::class, 'show']);
        Route::post('/shifts/{shift}/close', [ShiftController::class, 'close']);
        Route::post('/shifts/{shift}/cash-movements', [ShiftController::class, 'addCashMovement']);

        Route::get('/sales', [SaleController::class, 'index']);
        Route::post('/sales', [SaleController::class, 'store']);
        Route::get('/sales/{sale}', [SaleController::class, 'show']);
        Route::post('/sales/{sale}/void', [SaleController::class, 'void']);
        Route::post('/sales/{sale}/payments', [SaleController::class, 'addPayment']);
        Route::post('/sales/{sale}/refunds', [SaleController::class, 'refund']);

        Route::get('/discounts/calculate', [DiscountController::class, 'calculate']);

        // Buy-back / refurb
        Route::apiResource('acquisitions', AcquisitionController::class)
            ->except(['destroy']);
        Route::post('/acquisitions/{acquisition}/imei-check', [AcquisitionController::class, 'imeiCheck']);
        Route::post('/acquisitions/{acquisition}/complete', [AcquisitionController::class, 'complete']);

        Route::get('/refurb-jobs', [RefurbJobController::class, 'index']);
        Route::post('/refurb-jobs', [RefurbJobController::class, 'store']);
        Route::get('/refurb-jobs/{refurbJob}', [RefurbJobController::class, 'show']);
        Route::post('/refurb-jobs/{refurbJob}/lines', [RefurbJobController::class, 'addLine']);
        Route::post('/refurb-jobs/{refurbJob}/complete', [RefurbJobController::class, 'complete']);

        // Installments
        Route::get('/installment-plans', [InstallmentPlanController::class, 'index']);
        Route::post('/installment-plans', [InstallmentPlanController::class, 'store']);
        Route::get('/installment-plans/{installmentPlan}', [InstallmentPlanController::class, 'show']);
        Route::post('/installment-plans/{installmentPlan}/schedules/{schedule}/pay', [InstallmentPlanController::class, 'pay']);

        // Reports — all read-only; see ReportService's docblock for why
        // these compute live instead of reading the (still-unpopulated)
        // rollup tables.
        Route::get('/reports/sales', [ReportController::class, 'sales']);
        Route::get('/reports/margin', [ReportController::class, 'margin']);
        Route::get('/reports/technician-throughput', [ReportController::class, 'technicianThroughput']);
        Route::get('/reports/most-repaired-models', [ReportController::class, 'mostRepairedModels']);
        Route::get('/reports/warranty-failure-rate', [ReportController::class, 'warrantyFailureRate']);
        Route::get('/reports/inventory-valuation', [ReportController::class, 'inventoryValuation']);
        Route::get('/reports/dead-stock', [ReportController::class, 'deadStock']);
        Route::get('/reports/unclaimed-aging', [ReportController::class, 'unclaimedAging']);
        Route::get('/reports/commissions-payable', [ReportController::class, 'commissionsPayable']);
    });

    // Exists only so the exception-handling test suite can assert the 500
    // path renders JSON like everything else. Never reachable outside tests.
    if (app()->runningUnitTests()) {
        Route::get('/_test/boom', function (): never {
            throw new RuntimeException('Boom — forced failure for exception-handling tests.');
        });
    }
});
