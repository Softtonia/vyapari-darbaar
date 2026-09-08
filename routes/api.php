<?php

use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\AdminProfileController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\CommodityCategoryController;
use App\Http\Controllers\Api\Admin\CommodityController;
use App\Http\Controllers\Api\Admin\CommoditySubcategoryController;
use App\Http\Controllers\Api\Admin\EmailTemplateController;
use App\Http\Controllers\Api\Admin\RoleController;
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
        Route::post('profile/send-email-otp', [AdminProfileController::class, 'sendEmailOtp'])
            ->name('admin.profile.send-email-otp');
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
            Route::get('placeholders', [EmailTemplateController::class, 'placeholders'])
                ->name('admin.email-templates.placeholders');
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

        // Role management
        Route::prefix('roles')->group(function () {
            Route::get('/', [RoleController::class, 'index'])
                ->name('admin.roles.index');
            Route::post('/', [RoleController::class, 'store'])
                ->name('admin.roles.store');
            Route::post('bulk-delete', [RoleController::class, 'bulkDestroy'])
                ->name('admin.roles.bulk-delete');
            Route::delete('bulk-delete', [RoleController::class, 'bulkDestroy']);
            Route::get('{role}', [RoleController::class, 'show'])
                ->name('admin.roles.show');
            Route::put('{role}', [RoleController::class, 'update'])
                ->name('admin.roles.update');
            Route::delete('{role}', [RoleController::class, 'destroy'])
                ->name('admin.roles.destroy');
            Route::patch('{role}/status', [RoleController::class, 'updateStatus'])
                ->name('admin.roles.update-status');
        });

        // Commodity Category management
        Route::prefix('commodity-categories')->group(function () {
            Route::get('options', [CommodityCategoryController::class, 'options'])
                ->name('admin.commodity-categories.options');
            Route::get('/', [CommodityCategoryController::class, 'index'])
                ->name('admin.commodity-categories.index');
            Route::post('/', [CommodityCategoryController::class, 'store'])
                ->name('admin.commodity-categories.store');
            Route::post('bulk-delete', [CommodityCategoryController::class, 'bulkDestroy'])
                ->name('admin.commodity-categories.bulk-delete');
            Route::patch('bulk-status', [CommodityCategoryController::class, 'bulkStatus'])
                ->name('admin.commodity-categories.bulk-status');
            Route::get('{commodityCategory}', [CommodityCategoryController::class, 'show'])
                ->name('admin.commodity-categories.show');
            Route::put('{commodityCategory}', [CommodityCategoryController::class, 'update'])
                ->name('admin.commodity-categories.update');
            Route::delete('{commodityCategory}', [CommodityCategoryController::class, 'destroy'])
                ->name('admin.commodity-categories.destroy');
            Route::patch('{commodityCategory}/status', [CommodityCategoryController::class, 'updateStatus'])
                ->name('admin.commodity-categories.update-status');
        });

        // Commodity management
        Route::prefix('commodities')->group(function () {
            Route::get('options', [CommodityController::class, 'options'])
                ->name('admin.commodities.options');
            Route::get('/', [CommodityController::class, 'index'])
                ->name('admin.commodities.index');
            Route::post('/', [CommodityController::class, 'store'])
                ->name('admin.commodities.store');
            Route::post('bulk-delete', [CommodityController::class, 'bulkDestroy'])
                ->name('admin.commodities.bulk-delete');
            Route::patch('bulk-status', [CommodityController::class, 'bulkStatus'])
                ->name('admin.commodities.bulk-status');
            Route::get('{commodity}', [CommodityController::class, 'show'])
                ->name('admin.commodities.show');
            Route::put('{commodity}', [CommodityController::class, 'update'])
                ->name('admin.commodities.update');
            Route::delete('{commodity}', [CommodityController::class, 'destroy'])
                ->name('admin.commodities.destroy');
            Route::patch('{commodity}/status', [CommodityController::class, 'updateStatus'])
                ->name('admin.commodities.update-status');
        });

        // Commodity Subcategory management
        Route::prefix('commodity-subcategories')->group(function () {
            Route::get('options', [CommoditySubcategoryController::class, 'options'])
                ->name('admin.commodity-subcategories.options');
            Route::get('/', [CommoditySubcategoryController::class, 'index'])
                ->name('admin.commodity-subcategories.index');
            Route::post('/', [CommoditySubcategoryController::class, 'store'])
                ->name('admin.commodity-subcategories.store');
            Route::post('bulk-delete', [CommoditySubcategoryController::class, 'bulkDestroy'])
                ->name('admin.commodity-subcategories.bulk-delete');
            Route::patch('bulk-status', [CommoditySubcategoryController::class, 'bulkStatus'])
                ->name('admin.commodity-subcategories.bulk-status');
            Route::get('{commoditySubcategory}', [CommoditySubcategoryController::class, 'show'])
                ->name('admin.commodity-subcategories.show');
            Route::put('{commoditySubcategory}', [CommoditySubcategoryController::class, 'update'])
                ->name('admin.commodity-subcategories.update');
            Route::delete('{commoditySubcategory}', [CommoditySubcategoryController::class, 'destroy'])
                ->name('admin.commodity-subcategories.destroy');
            Route::patch('{commoditySubcategory}/status', [CommoditySubcategoryController::class, 'updateStatus'])
                ->name('admin.commodity-subcategories.update-status');
        });
    });
});

/*
|--------------------------------------------------------------------------
| User API Routes
|--------------------------------------------------------------------------
*/
Route::prefix('user')->group(function () {
    // Public user authentication & password recovery
    Route::post('login', [UserAuthController::class, 'login'])
        ->middleware('throttle:user-login')
        ->name('user.login');

    Route::post('forgot-password', [UserAuthController::class, 'forgotPassword'])
        ->middleware('throttle:user-password-reset')
        ->name('user.forgot-password');

    Route::post('reset-password', [UserAuthController::class, 'resetPassword'])
        ->middleware('throttle:user-password-reset')
        ->name('user.reset-password');

    // Protected user endpoints
    Route::middleware(['auth:sanctum', 'user'])->group(function () {
        // Profile management
        Route::get('profile', [UserProfileController::class, 'profile'])
            ->middleware('throttle:user-api')
            ->name('user.profile');
        Route::post('profile/send-email-otp', [UserProfileController::class, 'sendEmailOtp'])
            ->name('user.profile.send-email-otp');
        Route::patch('profile', [UserProfileController::class, 'update'])
            ->name('user.profile.update');

        Route::post('change-password', [UserAuthController::class, 'changePassword'])
            ->middleware('throttle:user-change-password')
            ->name('user.change-password');

        Route::post('logout', [UserAuthController::class, 'logout'])
            ->name('user.logout');
    });
});
