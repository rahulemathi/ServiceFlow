<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\WorkOrderApiController;
use App\Http\Middleware\EnsureApiTenantScope;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ServiceFlow REST API v1 Routes
|--------------------------------------------------------------------------
|
| Versioned REST API endpoints for technician mobile apps and integrations.
| Protected by Sanctum token authentication, rate limiting, and tenant scoping.
|
*/

Route::prefix('v1')->middleware(['throttle:60,1'])->group(function (): void {
    // Public Authentication
    Route::post('/auth/login', [AuthController::class, 'login'])->name('api.v1.auth.login');

    // Authenticated Routes
    Route::middleware(['auth:sanctum'])->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');

        // Tenant-Scoped Resources
        Route::middleware([EnsureApiTenantScope::class])->group(function (): void {
            Route::get('/work-orders', [WorkOrderApiController::class, 'index'])->name('api.v1.work-orders.index');
            Route::get('/work-orders/{id}', [WorkOrderApiController::class, 'show'])->name('api.v1.work-orders.show');
            Route::post('/work-orders/{id}/status', [WorkOrderApiController::class, 'updateStatus'])->name('api.v1.work-orders.status');
            Route::post('/work-orders/{id}/checklists/{checklistId}/toggle', [WorkOrderApiController::class, 'toggleChecklist'])->name('api.v1.work-orders.checklists.toggle');
            Route::post('/work-orders/{id}/entries', [WorkOrderApiController::class, 'addEntry'])->name('api.v1.work-orders.entries');
            Route::post('/work-orders/{id}/sign', [WorkOrderApiController::class, 'sign'])->name('api.v1.work-orders.sign');
        });
    });
});
