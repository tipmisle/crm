<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\IntegrationController;
use App\Http\Controllers\Admin\SupportContentController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WorkspaceController;
use Illuminate\Support\Facades\Route;

// Deny-by-default: every route below requires authentication AND the
// explicit is_platform_admin flag (see EnsurePlatformAdmin). Workspace
// roles never satisfy this. See docs/admin-security.md.
Route::middleware(['auth', 'platform.admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::get('/workspaces/{workspace}', [WorkspaceController::class, 'show'])->name('workspaces.show');

    Route::get('/workspaces/{workspace}/support/conversations/{conversation}', [SupportContentController::class, 'conversation'])->name('workspaces.support.conversation');
    Route::get('/workspaces/{workspace}/support/customers/{customer}', [SupportContentController::class, 'customer'])->name('workspaces.support.customer');
    Route::get('/workspaces/{workspace}/support/orders/{order}', [SupportContentController::class, 'order'])->name('workspaces.support.order');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');

    Route::get('/integrations', [IntegrationController::class, 'index'])->name('integrations.index');

    Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');

    // Leaving support mode is never gated — an admin must always be able to
    // drop out of customer-content access immediately.
    Route::post('/support/end', [WorkspaceController::class, 'endSupportSession'])->name('support.end');

    // Sensitive mutations (starting to view customer content, deactivating
    // accounts, deleting a demo workspace) require a recently-confirmed
    // password on top of the platform-admin gate above.
    Route::middleware('password.confirm')->group(function () {
        Route::post('/workspaces/{workspace}/support/start', [WorkspaceController::class, 'startSupportSession'])->name('workspaces.support.start');
        Route::delete('/workspaces/{workspace}/demo', [WorkspaceController::class, 'destroyDemo'])->name('workspaces.destroy-demo');

        Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
        Route::post('/users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');

        Route::post('/integrations/{integration}/clear-error', [IntegrationController::class, 'clearError'])->name('integrations.clear-error');
    });
});
