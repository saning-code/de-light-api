<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminController;

// ─── Public routes ────────────────────────────────────────────────────────────
Route::get('/', fn() => redirect('/admin/login'));
Route::get('/admin/login',  [AdminController::class, 'showLogin']);
Route::post('/admin/login', [AdminController::class, 'loginJson']);

// ─── Dashboard HTML shell (no server-side auth — client checks localStorage) ──
Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);

// ─── Admin API — protected by Bearer token via SuperAdminAuth middleware ───────
Route::prefix('admin/api')->middleware(\App\Http\Middleware\SuperAdminAuth::class)->group(function () {
    Route::get('/stats',                      [AdminController::class, 'apiStats']);
    Route::get('/tenants',                    [AdminController::class, 'apiTenants']);
    Route::get('/tenants/{id}',               [AdminController::class, 'apiTenant']);
    Route::post('/tenants/{id}/suspend',      [AdminController::class, 'apiSuspendTenant']);
    Route::post('/tenants/{id}/activate',     [AdminController::class, 'apiActivateTenant']);
    Route::post('/tenants/{id}/extend-trial', [AdminController::class, 'apiExtendTrial']);
    Route::delete('/tenants/{id}',            [AdminController::class, 'apiDeleteTenant']);
    Route::get('/plans',                      [AdminController::class, 'apiPlans']);
    Route::put('/plans/{id}',                 [AdminController::class, 'apiUpdatePlan']);
    Route::get('/recent-signups',             [AdminController::class, 'apiRecentSignups']);
    Route::get('/chart-data',                 [AdminController::class, 'apiChartData']);
});
