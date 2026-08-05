<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\SaleController;
use App\Http\Controllers\API\ReportController;
use App\Http\Controllers\API\SyncController;

/*
|--------------------------------------------------------------------------
| API Routes — De-Light Smart Business Manager (SaaS)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ─── Public Auth Routes ───────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/pin-login', [AuthController::class, 'pinLogin']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });

    // ─── Protected Routes (JWT Required) ──────────────────────────────────
    Route::middleware(['auth:api'])->group(function () {

        // Auth management
        Route::prefix('auth')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/set-pin', [AuthController::class, 'setPin']);
        });

        // Dashboard & Analytics
        Route::prefix('reports')->group(function () {
            Route::get('/dashboard', [ReportController::class, 'dashboard']);
            Route::get('/period', [ReportController::class, 'period']);
        });

        // Products & Stock Management
        Route::prefix('products')->group(function () {
            Route::get('/', [ProductController::class, 'index']);
            Route::post('/', [ProductController::class, 'store']);
            Route::get('/stock-value', [ProductController::class, 'stockValue']);
            Route::get('/barcode/{barcode}', [ProductController::class, 'lookupBarcode']);
            Route::post('/bulk-import', [ProductController::class, 'bulkImport']);
            Route::get('/{id}', [ProductController::class, 'show']);
            Route::put('/{id}', [ProductController::class, 'update']);
            Route::delete('/{id}', [ProductController::class, 'destroy']);
            Route::post('/{id}/adjust-stock', [ProductController::class, 'adjustStock']);
        });

        // POS Sales
        Route::prefix('sales')->group(function () {
            Route::get('/', [SaleController::class, 'index']);
            Route::post('/', [SaleController::class, 'store']);
            Route::get('/today-summary', [SaleController::class, 'todaySummary']);
            Route::get('/{id}', [SaleController::class, 'show']);
            Route::post('/{id}/void', [SaleController::class, 'void']);
            Route::get('/{id}/receipt', [SaleController::class, 'receipt']);
            Route::get('/{id}/receipt/pdf', [SaleController::class, 'receiptPdf']);
        });

        // Background Offline Sync Engine
        Route::prefix('sync')->group(function () {
            Route::post('/push', [SyncController::class, 'push']);
            Route::get('/pull', [SyncController::class, 'pull']);
            Route::get('/status', [SyncController::class, 'status']);
        });

    });
});

// Health check endpoint
Route::get('/ping', fn() => response()->json(['status' => 'online', 'app' => 'De-Light API v1']));
