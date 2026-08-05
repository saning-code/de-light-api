<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminController;

// ─── Public ───────────────────────────────────────────────────────────────────
Route::get('/', fn() => redirect('/admin/login'));

// ─── Admin Login (no auth required) ──────────────────────────────────────────
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login',  [AdminController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminController::class, 'login'])->name('login.post');
    Route::post('/logout',[AdminController::class, 'logout'])->name('logout');
});

// ─── Admin Protected Routes ───────────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->middleware(\App\Http\Middleware\SuperAdminAuth::class)->group(function () {

    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');

    // ── AJAX API endpoints called by the dashboard JS ─────────────────────
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/stats',                          [AdminController::class, 'apiStats']);
        Route::get('/tenants',                        [AdminController::class, 'apiTenants']);
        Route::get('/tenants/{id}',                   [AdminController::class, 'apiTenant']);
        Route::post('/tenants/{id}/suspend',          [AdminController::class, 'apiSuspendTenant']);
        Route::post('/tenants/{id}/activate',         [AdminController::class, 'apiActivateTenant']);
        Route::post('/tenants/{id}/extend-trial',     [AdminController::class, 'apiExtendTrial']);
        Route::delete('/tenants/{id}',                [AdminController::class, 'apiDeleteTenant']);
        Route::get('/plans',                          [AdminController::class, 'apiPlans']);
        Route::put('/plans/{id}',                     [AdminController::class, 'apiUpdatePlan']);
        Route::get('/recent-signups',                 [AdminController::class, 'apiRecentSignups']);
        Route::get('/chart-data',                     [AdminController::class, 'apiChartData']);
    });
});
