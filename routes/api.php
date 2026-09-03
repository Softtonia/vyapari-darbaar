<?php

use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\AdminProfileController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\EmailTemplateController;
use App\Http\Controllers\Api\User\UserAuthController;
use App\Http\Controllers\Api\User\UserProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin API Routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->group(function () {
    // Public admin authentication & password management endpoints
    Route::post('login', [AdminAuthController::class, 'login'])
        ->middleware('throttle:admin-login')
        ->name('admin.login');

    Route::post('forgot-password', [AdminAuthController::class, 'forgotPassword'])
        ->middleware('throttle:admin-password-reset')
        ->name('admin.forgot-password');

    Route::post('reset-password', [AdminAuthController::class, 'resetPassword'])
        ->middleware('throttle:admin-password-reset')
        ->name('admin.reset-password');

    // Protected admin endpoints
    Route::middleware(['auth:sanctum', 'admin', 'throttle:admin-api'])->group(function () {
        // Profile management
        Route::get('profile', [AdminProfileController::class, 'profile'])
            ->name('admin.profile');
        Route::patch('profile', [AdminProfileController::class, 'update'])
            ->name('admin.profile.update');

        // Session management
        Route::post('logout', [AdminAuthController::class, 'logout'])
            ->name('admin.logout');
        Route::post('logout-all', [AdminAuthController::class, 'logoutAll'])
            ->name('admin.logout-all');

        // Email templates management
        Route::prefix('email-templates')->group(function () {
            Route::get('/', [EmailTemplateController::class, 'index'])
                ->name('admin.email-templates.index');
            Route::post('/', [EmailTemplateController::class, 'store'])
                ->name('admin.email-templates.store');
            Route::post('bulk-delete', [EmailTemplateController::class, 'bulkDestroy'])
                ->name('admin.email-templates.bulk-delete');
            Route::delete('bulk-delete', [EmailTemplateController::class, 'bulkDestroy']);
            Route::post('preview', [EmailTemplateController::class, 'preview'])
                ->name('admin.email-templates.preview');
            Route::get('by-key/{key}', [EmailTemplateController::class, 'byKey'])
                ->name('admin.email-templates.by-key');
            Route::get('{emailTemplate}', [EmailTemplateController::class, 'show'])
                ->name('admin.email-templates.show');
            Route::put('{emailTemplate}', [EmailTemplateController::class, 'update'])
                ->name('admin.email-templates.update');
            Route::delete('{emailTemplate}', [EmailTemplateController::class, 'destroy'])
                ->name('admin.email-templates.destroy');
            Route::patch('{emailTemplate}/status', [EmailTemplateController::class, 'updateStatus'])
                ->name('admin.email-templates.update-status');
        });

        // User management
        Route::prefix('users')->group(function () {
            Route::get('/', [AdminUserController::class, 'index'])
                ->name('admin.users.index');
            Route::post('/', [AdminUserController::class, 'store'])
                ->middleware('throttle:admin-user-create')
                ->name('admin.users.store');
            Route::post('bulk-delete', [AdminUserController::class, 'bulkDestroy'])
                ->name('admin.users.bulk-delete');
            Route::delete('bulk-delete', [AdminUserController::class, 'bulkDestroy']);
            Route::get('{user}', [AdminUserController::class, 'show'])
                ->name('admin.users.show');
            Route::put('{user}', [AdminUserController::class, 'update'])
                ->name('admin.users.update');
            Route::delete('{user}', [AdminUserController::class, 'destroy'])
                ->name('admin.users.destroy');
            Route::patch('{user}/status', [AdminUserController::class, 'updateStatus'])
                ->name('admin.users.update-status');
            Route::post('{user}/resend-credentials', [AdminUserController::class, 'resendCredentials'])
                ->middleware('throttle:admin-user-resend-credentials')
                ->name('admin.users.resend-credentials');
        });
    });
});

/*
|--------------------------------------------------------------------------
| User API Routes
|--------------------------------------------------------------------------
*/
Route::prefix('user')->group(function () {
    // Public user login
    Route::post('login', [UserAuthController::class, 'login'])
        ->middleware('throttle:user-login')
        ->name('user.login');

    // Protected user endpoints
    Route::middleware(['auth:sanctum', 'user'])->group(function () {
        Route::get('profile', [UserProfileController::class, 'profile'])
            ->middleware('throttle:user-api')
            ->name('user.profile');

        Route::post('change-password', [UserAuthController::class, 'changePassword'])
            ->middleware('throttle:user-change-password')
            ->name('user.change-password');

        Route::post('logout', [UserAuthController::class, 'logout'])
            ->name('user.logout');
    });
});
