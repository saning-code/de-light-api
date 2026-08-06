<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\SaleController;
use App\Http\Controllers\API\ReportController;
use App\Http\Controllers\API\SyncController;
use App\Http\Controllers\Admin\AdminController;

use App\Http\Controllers\API\UserController;

/*
|--------------------------------------------------------------------------
| API Routes — De-Light Smart Business Manager (SaaS)
|--------------------------------------------------------------------------
*/

// ─── Health check ─────────────────────────────────────────────────────────────
Route::get('/ping', fn() => response()->json(['status' => 'online', 'app' => 'De-Light API v1']));

// ─── Shop API ─────────────────────────────────────────────────────────────────
Route::prefix('v1')->group(function () {

    // Public Auth
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login',    [AuthController::class, 'login']);
        Route::post('/pin-login',[AuthController::class, 'pinLogin']);
        Route::post('/refresh',  [AuthController::class, 'refresh']);
    });

    // Protected (JWT)
    Route::middleware(['auth:api'])->group(function () {

        Route::prefix('auth')->group(function () {
            Route::get('/me',        [AuthController::class, 'me']);
            Route::post('/logout',   [AuthController::class, 'logout']);
            Route::post('/set-pin',  [AuthController::class, 'setPin']);
        });

        Route::prefix('reports')->group(function () {
            Route::get('/dashboard', [ReportController::class, 'dashboard']);
            Route::get('/period',    [ReportController::class, 'period']);
        });

        Route::prefix('products')->group(function () {
            Route::get('/',                       [ProductController::class, 'index']);
            Route::post('/',                      [ProductController::class, 'store']);
            Route::get('/stock-value',            [ProductController::class, 'stockValue']);
            Route::get('/barcode/{barcode}',      [ProductController::class, 'lookupBarcode']);
            Route::post('/bulk-import',           [ProductController::class, 'bulkImport']);
            Route::get('/{id}',                   [ProductController::class, 'show']);
            Route::put('/{id}',                   [ProductController::class, 'update']);
            Route::delete('/{id}',                [ProductController::class, 'destroy']);
            Route::post('/{id}/adjust-stock',     [ProductController::class, 'adjustStock']);
        });

        Route::prefix('sales')->group(function () {
            Route::get('/',                       [SaleController::class, 'index']);
            Route::post('/',                      [SaleController::class, 'store']);
            Route::get('/today-summary',          [SaleController::class, 'todaySummary']);
            Route::get('/{id}',                   [SaleController::class, 'show']);
            Route::post('/{id}/void',             [SaleController::class, 'void']);
            Route::get('/{id}/receipt',           [SaleController::class, 'receipt']);
            Route::get('/{id}/receipt/pdf',       [SaleController::class, 'receiptPdf']);
        });

        Route::prefix('sync')->group(function () {
            Route::post('/push',  [SyncController::class, 'push']);
            Route::get('/pull',   [SyncController::class, 'pull']);
            Route::get('/status', [SyncController::class, 'status']);
        });

        // User Management
        Route::prefix('users')->group(function () {
            Route::get('/',          [UserController::class, 'index']);
            Route::post('/',         [UserController::class, 'store']);
            Route::put('/{id}',      [UserController::class, 'update']);
            Route::post('/{id}/toggle', [UserController::class, 'toggle']);
        });
    });
});

// ─── Admin Panel (HTML + API — no web middleware) ─────────────────────────────
Route::prefix('admin')->group(function () {

    // Serve HTML pages
    Route::get('/login',     [AdminController::class, 'showLogin']);
    Route::get('/dashboard', [AdminController::class, 'dashboard']);

    // Login API (public)
    Route::post('/login',  [AdminController::class, 'loginJson']);

    // Protected admin API (Bearer token)
    Route::middleware(\App\Http\Middleware\SuperAdminAuth::class)->group(function () {
        Route::get('/api/stats',                      [AdminController::class, 'apiStats']);
        Route::get('/api/tenants',                    [AdminController::class, 'apiTenants']);
        Route::get('/api/tenants/{id}',               [AdminController::class, 'apiTenant']);
        Route::post('/api/tenants/{id}/suspend',      [AdminController::class, 'apiSuspendTenant']);
        Route::post('/api/tenants/{id}/activate',     [AdminController::class, 'apiActivateTenant']);
        Route::post('/api/tenants/{id}/extend-trial', [AdminController::class, 'apiExtendTrial']);
        Route::delete('/api/tenants/{id}',            [AdminController::class, 'apiDeleteTenant']);
        Route::get('/api/plans',                      [AdminController::class, 'apiPlans']);
        Route::put('/api/plans/{id}',                 [AdminController::class, 'apiUpdatePlan']);
        Route::get('/api/recent-signups',             [AdminController::class, 'apiRecentSignups']);
        Route::get('/api/chart-data',                 [AdminController::class, 'apiChartData']);
    });
});
