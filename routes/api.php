<?php

use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\AdminCompanyController;
use App\Http\Controllers\Api\Admin\AdminInAppNotificationController;
use App\Http\Controllers\Api\Admin\AdminNotificationDeviceController;
use App\Http\Controllers\Api\Admin\AdminProfileController;
use App\Http\Controllers\Api\Admin\AdminUserActivityController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\CommodityCategoryController;
use App\Http\Controllers\Api\Admin\CommodityController;
use App\Http\Controllers\Api\Admin\CommodityGradeController;
use App\Http\Controllers\Api\Admin\CommoditySubcategoryController;
use App\Http\Controllers\Api\Admin\CommodityVarietyController;
use App\Http\Controllers\Api\Admin\DistrictController;
use App\Http\Controllers\Api\Admin\EmailTemplateController;
use App\Http\Controllers\Api\Admin\ExchangeCommodityMappingController;
use App\Http\Controllers\Api\Admin\ExchangeController;
use App\Http\Controllers\Api\Admin\ExchangeInstrumentController;
use App\Http\Controllers\Api\Admin\FirebaseSettingController;
use App\Http\Controllers\Api\Admin\MandiController;
use App\Http\Controllers\Api\Admin\MarketIngestionRunController;
use App\Http\Controllers\Api\Admin\NotificationBatchController;
use App\Http\Controllers\Api\Admin\NotificationDashboardController;
use App\Http\Controllers\Api\Admin\NotificationLogController;
use App\Http\Controllers\Api\Admin\NotificationSendController;
use App\Http\Controllers\Api\Admin\NotificationTemplateController;
use App\Http\Controllers\Api\Admin\NotificationTopicController;
use App\Http\Controllers\Api\Admin\NewsArticleController as AdminNewsArticleController;
use App\Http\Controllers\Api\Admin\NewsCategoryController as AdminNewsCategoryController;
use App\Http\Controllers\Api\Admin\NewsImportRunController;
use App\Http\Controllers\Api\Admin\NewsMediaController as AdminNewsMediaController;
use App\Http\Controllers\Api\Admin\NewsSourceController as AdminNewsSourceController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\SiteSettingController as AdminSiteSettingController;
use App\Http\Controllers\Api\Admin\SmtpSettingController;
use App\Http\Controllers\Api\Admin\StateController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\PublicExchangeController;
use App\Http\Controllers\Api\PublicFirebaseConfigController;
use App\Http\Controllers\Api\Public\MarketHistoryController;
use App\Http\Controllers\Api\SiteSettingController;
use App\Http\Controllers\Api\User\InAppNotificationController;
use App\Http\Controllers\Api\User\NotificationDeviceController;
use App\Http\Controllers\Api\User\UserActivityController;
use App\Http\Controllers\Api\User\UserAuthController;
use App\Http\Controllers\Api\User\UserCompanyController;
use App\Http\Controllers\Api\User\UserProfileController;
use Illuminate\Support\Facades\Route;

// Universal Direct Aliases for Mobile/Email OTP Login
Route::post('send-otp', [UserAuthController::class, 'sendOtp'])->middleware('throttle:user-send-otp');
Route::post('send-login-otp', [UserAuthController::class, 'sendLoginOtp'])->middleware('throttle:user-send-otp');
Route::post('verify-otp', [UserAuthController::class, 'verifyOtp'])->middleware('throttle:user-verify-otp');
Route::post('login-with-otp', [UserAuthController::class, 'loginWithOtp'])->middleware('throttle:user-login');

/*
|--------------------------------------------------------------------------
| Unified Auth API Routes (/api/auth/admin/* and /api/auth/user/*)
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    // Direct Auth Aliases (/api/auth/send-otp, /api/auth/login-with-otp, etc.)
    Route::post('send-otp', [UserAuthController::class, 'sendOtp'])->middleware('throttle:user-send-otp');
    Route::post('send-login-otp', [UserAuthController::class, 'sendLoginOtp'])->middleware('throttle:user-send-otp');
    Route::post('verify-otp', [UserAuthController::class, 'verifyOtp'])->middleware('throttle:user-verify-otp');
    Route::post('login-with-otp', [UserAuthController::class, 'loginWithOtp'])->middleware('throttle:user-login');

    // Admin Auth
    Route::prefix('admin')->group(function () {
        Route::post('login', [AdminAuthController::class, 'login'])
            ->middleware('throttle:admin-login')
            ->name('auth.admin.login');
        Route::post('login-with-otp', [AdminAuthController::class, 'loginWithOtp'])
            ->middleware('throttle:admin-login')
            ->name('auth.admin.login-with-otp');
        Route::post('send-otp', [AdminAuthController::class, 'sendOtp'])
            ->middleware('throttle:admin-send-otp')
            ->name('auth.admin.send-otp');
        Route::post('send-login-otp', [AdminAuthController::class, 'sendLoginOtp'])
            ->middleware('throttle:admin-send-otp')
            ->name('auth.admin.send-login-otp');
        Route::post('otp/send', [AdminAuthController::class, 'sendOtp'])
            ->middleware('throttle:admin-send-otp');

        Route::post('forgot-password', [AdminAuthController::class, 'forgotPassword'])
            ->middleware('throttle:admin-password-reset')
            ->name('auth.admin.forgot-password');

        Route::post('reset-password', [AdminAuthController::class, 'resetPassword'])
            ->middleware('throttle:admin-password-reset')
            ->name('auth.admin.reset-password');

        Route::match(['get', 'post'], 'verify-reset-token', [AdminAuthController::class, 'verifyResetToken'])
            ->middleware('throttle:admin-password-reset')
            ->name('auth.admin.verify-reset-token');

        Route::middleware(['auth:sanctum', 'admin', 'throttle:admin-api'])->group(function () {
            Route::post('logout', [AdminAuthController::class, 'logout'])
                ->name('auth.admin.logout');
            Route::post('logout-all', [AdminAuthController::class, 'logoutAll'])
                ->name('auth.admin.logout-all');
        });
    });

    // User Auth
    Route::prefix('user')->group(function () {
        Route::post('send-otp', [UserAuthController::class, 'sendOtp'])
            ->middleware('throttle:user-send-otp')
            ->name('auth.user.send-otp');
        Route::post('send-login-otp', [UserAuthController::class, 'sendLoginOtp'])
            ->middleware('throttle:user-send-otp')
            ->name('auth.user.send-login-otp');
        Route::post('otp/send', [UserAuthController::class, 'sendOtp'])
            ->middleware('throttle:user-send-otp');

        Route::post('verify-otp', [UserAuthController::class, 'verifyOtp'])
            ->middleware('throttle:user-verify-otp')
            ->name('auth.user.verify-otp');
        Route::post('otp/verify', [UserAuthController::class, 'verifyOtp'])
            ->middleware('throttle:user-verify-otp');

        Route::post('validate-username', [UserAuthController::class, 'validateUsername'])
            ->middleware('throttle:user-api')
            ->name('auth.user.validate-username');

        Route::post('register', [UserAuthController::class, 'register'])
            ->middleware('throttle:user-register')
            ->name('auth.user.register');

        Route::post('login', [UserAuthController::class, 'login'])
            ->middleware('throttle:user-login')
            ->name('auth.user.login');

        Route::post('login-with-otp', [UserAuthController::class, 'loginWithOtp'])
            ->middleware('throttle:user-login')
            ->name('auth.user.login-with-otp');

        Route::post('forgot-password', [UserAuthController::class, 'forgotPassword'])
            ->middleware('throttle:user-password-reset')
            ->name('auth.user.forgot-password');

        Route::post('reset-password', [UserAuthController::class, 'resetPassword'])
            ->middleware('throttle:user-password-reset')
            ->name('auth.user.reset-password');

        Route::match(['get', 'post'], 'verify-reset-token', [UserAuthController::class, 'verifyResetToken'])
            ->middleware('throttle:user-password-reset')
            ->name('auth.user.verify-reset-token');

        Route::middleware(['auth:sanctum', 'user'])->group(function () {
            Route::post('refresh-token', [UserAuthController::class, 'refreshToken'])
                ->middleware('throttle:user-api')
                ->name('auth.user.refresh-token');

            Route::post('change-password', [UserAuthController::class, 'changePassword'])
                ->middleware('throttle:user-change-password')
                ->name('auth.user.change-password');

            Route::post('logout', [UserAuthController::class, 'logout'])
                ->middleware('throttle:user-api')
                ->name('auth.user.logout');
        });
    });
});

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
    Route::post('login-with-otp', [AdminAuthController::class, 'loginWithOtp'])
        ->middleware('throttle:admin-login')
        ->name('admin.login-with-otp');
    Route::post('send-otp', [AdminAuthController::class, 'sendOtp'])
        ->middleware('throttle:admin-send-otp')
        ->name('admin.send-otp');
    Route::post('send-login-otp', [AdminAuthController::class, 'sendLoginOtp'])
        ->middleware('throttle:admin-send-otp')
        ->name('admin.send-login-otp');
    Route::post('otp/send', [AdminAuthController::class, 'sendOtp'])
        ->middleware('throttle:admin-send-otp');

    Route::post('forgot-password', [AdminAuthController::class, 'forgotPassword'])
        ->middleware('throttle:admin-password-reset')
        ->name('admin.forgot-password');

    Route::post('reset-password', [AdminAuthController::class, 'resetPassword'])
        ->middleware('throttle:admin-password-reset')
        ->name('admin.reset-password');

    Route::match(['get', 'post'], 'verify-reset-token', [AdminAuthController::class, 'verifyResetToken'])
        ->middleware('throttle:admin-password-reset')
        ->name('admin.verify-reset-token');

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
            Route::get('{user}/activities', [AdminUserActivityController::class, 'userActivities'])
                ->name('admin.users.activities');
            Route::get('{user}/logins', [AdminUserActivityController::class, 'userLogins'])
                ->name('admin.users.logins');
        });

        // Activity & Login Management
        Route::prefix('activities')->group(function () {
            Route::get('/', [AdminUserActivityController::class, 'index'])
                ->name('admin.activities.index');
            Route::get('own', [AdminUserActivityController::class, 'ownActivities'])
                ->name('admin.activities.own');
            Route::get('user/{user}', [AdminUserActivityController::class, 'userActivities'])
                ->name('admin.activities.user');
        });

        Route::prefix('logins')->group(function () {
            Route::get('/', [AdminUserActivityController::class, 'logins'])
                ->name('admin.logins.index');
            Route::get('own', [AdminUserActivityController::class, 'ownLogins'])
                ->name('admin.logins.own');
            Route::get('user/{user}', [AdminUserActivityController::class, 'userLogins'])
                ->name('admin.logins.user');
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

        // Commodity Variety management
        Route::prefix('commodity-varieties')->group(function () {
            Route::get('options', [CommodityVarietyController::class, 'options'])
                ->name('admin.commodity-varieties.options');
            Route::get('/', [CommodityVarietyController::class, 'index'])
                ->name('admin.commodity-varieties.index');
            Route::post('/', [CommodityVarietyController::class, 'store'])
                ->name('admin.commodity-varieties.store');
            Route::post('bulk-delete', [CommodityVarietyController::class, 'bulkDestroy'])
                ->name('admin.commodity-varieties.bulk-delete');
            Route::patch('bulk-status', [CommodityVarietyController::class, 'bulkStatus'])
                ->name('admin.commodity-varieties.bulk-status');
            Route::get('{commodityVariety}', [CommodityVarietyController::class, 'show'])
                ->name('admin.commodity-varieties.show');
            Route::put('{commodityVariety}', [CommodityVarietyController::class, 'update'])
                ->name('admin.commodity-varieties.update');
            Route::delete('{commodityVariety}', [CommodityVarietyController::class, 'destroy'])
                ->name('admin.commodity-varieties.destroy');
            Route::patch('{commodityVariety}/status', [CommodityVarietyController::class, 'updateStatus'])
                ->name('admin.commodity-varieties.update-status');
        });

        // Commodity Grade management
        Route::prefix('commodity-grades')->group(function () {
            Route::get('options', [CommodityGradeController::class, 'options'])
                ->name('admin.commodity-grades.options');
            Route::get('/', [CommodityGradeController::class, 'index'])
                ->name('admin.commodity-grades.index');
            Route::post('/', [CommodityGradeController::class, 'store'])
                ->name('admin.commodity-grades.store');
            Route::post('bulk-delete', [CommodityGradeController::class, 'bulkDestroy'])
                ->name('admin.commodity-grades.bulk-delete');
            Route::patch('bulk-status', [CommodityGradeController::class, 'bulkStatus'])
                ->name('admin.commodity-grades.bulk-status');
            Route::get('{commodityGrade}', [CommodityGradeController::class, 'show'])
                ->name('admin.commodity-grades.show');
            Route::put('{commodityGrade}', [CommodityGradeController::class, 'update'])
                ->name('admin.commodity-grades.update');
            Route::delete('{commodityGrade}', [CommodityGradeController::class, 'destroy'])
                ->name('admin.commodity-grades.destroy');
            Route::patch('{commodityGrade}/status', [CommodityGradeController::class, 'updateStatus'])
                ->name('admin.commodity-grades.update-status');
        });

        // State management
        Route::prefix('states')->group(function () {
            Route::get('options', [StateController::class, 'options'])
                ->name('admin.states.options');
            Route::get('/', [StateController::class, 'index'])
                ->name('admin.states.index');
            Route::post('/', [StateController::class, 'store'])
                ->name('admin.states.store');
            Route::post('bulk-delete', [StateController::class, 'bulkDestroy'])
                ->name('admin.states.bulk-delete');
            Route::patch('bulk-status', [StateController::class, 'bulkStatus'])
                ->name('admin.states.bulk-status');
            Route::get('{state}', [StateController::class, 'show'])
                ->name('admin.states.show');
            Route::put('{state}', [StateController::class, 'update'])
                ->name('admin.states.update');
            Route::delete('{state}', [StateController::class, 'destroy'])
                ->name('admin.states.destroy');
            Route::patch('{state}/status', [StateController::class, 'updateStatus'])
                ->name('admin.states.update-status');
        });

        // District management
        Route::prefix('districts')->group(function () {
            Route::get('options', [DistrictController::class, 'options'])
                ->name('admin.districts.options');
            Route::get('/', [DistrictController::class, 'index'])
                ->name('admin.districts.index');
            Route::post('/', [DistrictController::class, 'store'])
                ->name('admin.districts.store');
            Route::post('bulk-delete', [DistrictController::class, 'bulkDestroy'])
                ->name('admin.districts.bulk-delete');
            Route::patch('bulk-status', [DistrictController::class, 'bulkStatus'])
                ->name('admin.districts.bulk-status');
            Route::get('{district}', [DistrictController::class, 'show'])
                ->name('admin.districts.show');
            Route::put('{district}', [DistrictController::class, 'update'])
                ->name('admin.districts.update');
            Route::delete('{district}', [DistrictController::class, 'destroy'])
                ->name('admin.districts.destroy');
            Route::patch('{district}/status', [DistrictController::class, 'updateStatus'])
                ->name('admin.districts.update-status');
        });

        // Mandi management
        Route::prefix('mandis')->group(function () {
            Route::get('options', [MandiController::class, 'options'])
                ->name('admin.mandis.options');
            Route::get('/', [MandiController::class, 'index'])
                ->name('admin.mandis.index');
            Route::post('/', [MandiController::class, 'store'])
                ->name('admin.mandis.store');
            Route::post('bulk-delete', [MandiController::class, 'bulkDestroy'])
                ->name('admin.mandis.bulk-delete');
            Route::patch('bulk-status', [MandiController::class, 'bulkStatus'])
                ->name('admin.mandis.bulk-status');
            Route::get('{mandi}', [MandiController::class, 'show'])
                ->name('admin.mandis.show');
            Route::put('{mandi}', [MandiController::class, 'update'])
                ->name('admin.mandis.update');
            Route::delete('{mandi}', [MandiController::class, 'destroy'])
                ->name('admin.mandis.destroy');
            Route::patch('{mandi}/status', [MandiController::class, 'updateStatus'])
                ->name('admin.mandis.update-status');
        });

        // Site Settings management
        Route::prefix('site-settings')->group(function () {
            Route::get('/', [AdminSiteSettingController::class, 'show'])
                ->name('admin.site-settings.show');
            Route::patch('/', [AdminSiteSettingController::class, 'update'])
                ->name('admin.site-settings.update');
            Route::get('social-links', [AdminSiteSettingController::class, 'socialLinks'])
                ->name('admin.site-settings.social-links');
            Route::match(['put', 'patch'], 'social-links', [AdminSiteSettingController::class, 'updateSocialLinks'])
                ->name('admin.site-settings.update-social-links');
        });

        // System Activity Logs (Audit Trail)
        Route::prefix('activity-logs')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\Admin\AdminSystemActivityController::class, 'index'])
                ->name('admin.activity-logs.index');
            Route::get('modules', [\App\Http\Controllers\Api\Admin\AdminSystemActivityController::class, 'modules'])
                ->name('admin.activity-logs.modules');
            Route::get('actions', [\App\Http\Controllers\Api\Admin\AdminSystemActivityController::class, 'actions'])
                ->name('admin.activity-logs.actions');
            Route::post('bulk-delete', [\App\Http\Controllers\Api\Admin\AdminSystemActivityController::class, 'bulkDestroy'])
                ->name('admin.activity-logs.bulk-delete');
            Route::delete('bulk-delete', [\App\Http\Controllers\Api\Admin\AdminSystemActivityController::class, 'bulkDestroy']);
            Route::get('{id}', [\App\Http\Controllers\Api\Admin\AdminSystemActivityController::class, 'show'])
                ->name('admin.activity-logs.show');
            Route::delete('{id}', [\App\Http\Controllers\Api\Admin\AdminSystemActivityController::class, 'destroy'])
                ->name('admin.activity-logs.destroy');
        });

        // SMTP Settings management
        Route::prefix('settings/smtp')->group(function () {
            Route::get('/', [SmtpSettingController::class, 'show'])->name('admin.settings.smtp.show');
            Route::put('/', [SmtpSettingController::class, 'update'])->name('admin.settings.smtp.update');
            Route::post('test', [SmtpSettingController::class, 'test'])
                ->middleware('throttle:admin-smtp-test')
                ->name('admin.settings.smtp.test');
        });

        // Firebase Settings management
        Route::prefix('settings/firebase')->group(function () {
            Route::get('/', [FirebaseSettingController::class, 'show'])->name('admin.settings.firebase.show');
            Route::match(['put', 'post'], '/', [FirebaseSettingController::class, 'update'])->name('admin.settings.firebase.update');
            Route::post('test', [FirebaseSettingController::class, 'test'])
                ->middleware('throttle:admin-firebase-test')
                ->name('admin.settings.firebase.test');
        });

        // Notifications Management
        Route::prefix('notifications')->group(function () {
            // Dashboard
            Route::get('dashboard', [NotificationDashboardController::class, 'index'])
                ->name('admin.notifications.dashboard');

            // Send & Preview
            Route::post('preview', [NotificationSendController::class, 'preview'])
                ->middleware('throttle:admin-notification-preview')
                ->name('admin.notifications.preview');
            Route::post('send', [NotificationSendController::class, 'send'])
                ->middleware('throttle:admin-notification-send')
                ->name('admin.notifications.send');

            // In-App
            Route::prefix('in-app')->group(function () {
                Route::get('/', [AdminInAppNotificationController::class, 'index'])
                    ->name('admin.notifications.in-app.index');
                Route::get('{id}', [AdminInAppNotificationController::class, 'show'])
                    ->name('admin.notifications.in-app.show');
            });

            // Templates
            Route::prefix('templates')->group(function () {
                Route::get('/', [NotificationTemplateController::class, 'index'])
                    ->name('admin.notifications.templates.index');
                Route::post('/', [NotificationTemplateController::class, 'store'])
                    ->name('admin.notifications.templates.store');
                Route::post('bulk-delete', [NotificationTemplateController::class, 'bulkDestroy'])
                    ->name('admin.notifications.templates.bulk-delete');
                Route::delete('bulk-delete', [NotificationTemplateController::class, 'bulkDestroy']);
                Route::post('bulk', [NotificationTemplateController::class, 'bulkDestroy']);
                Route::delete('bulk', [NotificationTemplateController::class, 'bulkDestroy']);
                Route::get('{template}', [NotificationTemplateController::class, 'show'])
                    ->name('admin.notifications.templates.show');
                Route::put('{template}', [NotificationTemplateController::class, 'update'])
                    ->name('admin.notifications.templates.update');
                Route::delete('{template}', [NotificationTemplateController::class, 'destroy'])
                    ->name('admin.notifications.templates.destroy');
            });

            // Batches
            Route::prefix('batches')->group(function () {
                Route::get('/', [NotificationBatchController::class, 'index'])
                    ->name('admin.notifications.batches.index');
                Route::get('{batch}', [NotificationBatchController::class, 'show'])
                    ->name('admin.notifications.batches.show');
                Route::post('{batch}/cancel', [NotificationBatchController::class, 'cancel'])
                    ->name('admin.notifications.batches.cancel');
                Route::post('{batch}/retry-failed', [NotificationBatchController::class, 'retryFailed'])
                    ->name('admin.notifications.batches.retry-failed');
            });

            // Logs
            Route::prefix('logs')->group(function () {
                Route::get('/', [NotificationLogController::class, 'index'])
                    ->name('admin.notifications.logs.index');
                Route::get('{id}', [NotificationLogController::class, 'show'])
                    ->name('admin.notifications.logs.show');
            });

            // Devices
            Route::prefix('devices')->group(function () {
                Route::get('/', [AdminNotificationDeviceController::class, 'index'])
                    ->name('admin.notifications.devices.index');
                Route::post('bulk-delete', [AdminNotificationDeviceController::class, 'bulkDestroy'])
                    ->name('admin.notifications.devices.bulk-delete');
                Route::delete('bulk-delete', [AdminNotificationDeviceController::class, 'bulkDestroy']);
                Route::post('bulk', [AdminNotificationDeviceController::class, 'bulkDestroy']);
                Route::delete('bulk', [AdminNotificationDeviceController::class, 'bulkDestroy']);
                Route::get('{device}', [AdminNotificationDeviceController::class, 'show'])
                    ->name('admin.notifications.devices.show');
                Route::patch('{device}/status', [AdminNotificationDeviceController::class, 'updateStatus'])
                    ->name('admin.notifications.devices.update-status');
                Route::delete('{device}', [AdminNotificationDeviceController::class, 'destroy'])
                    ->name('admin.notifications.devices.destroy');
            });

            // Topics
            Route::prefix('topics')->group(function () {
                Route::get('/', [NotificationTopicController::class, 'index'])
                    ->name('admin.notifications.topics.index');
                Route::post('/', [NotificationTopicController::class, 'store'])
                    ->name('admin.notifications.topics.store');
                Route::get('{topic}', [NotificationTopicController::class, 'show'])
                    ->name('admin.notifications.topics.show');
                Route::put('{topic}', [NotificationTopicController::class, 'update'])
                    ->name('admin.notifications.topics.update');
                Route::delete('{topic}', [NotificationTopicController::class, 'destroy'])
                    ->name('admin.notifications.topics.destroy');
                Route::post('{topic}/users', [NotificationTopicController::class, 'addUsers'])
                    ->name('admin.notifications.topics.add-users');
                Route::delete('{topic}/users', [NotificationTopicController::class, 'removeUsers'])
                    ->name('admin.notifications.topics.remove-users');
            });
        });

        // Company Management
        Route::prefix('companies')->group(function () {
            Route::get('/', [AdminCompanyController::class, 'index'])
                ->name('admin.companies.index');
            Route::get('{id}', [AdminCompanyController::class, 'show'])
                ->name('admin.companies.show');
            Route::put('{id}', [AdminCompanyController::class, 'update'])
                ->name('admin.companies.update');
            Route::patch('{id}/status', [AdminCompanyController::class, 'updateStatus'])
                ->name('admin.companies.update-status');
            Route::delete('{id}', [AdminCompanyController::class, 'destroy'])
                ->name('admin.companies.destroy');
        });

        // Exchange Management
        Route::prefix('exchanges')->group(function () {
            Route::get('options', [ExchangeController::class, 'options'])
                ->name('admin.exchanges.options');
            Route::get('/', [ExchangeController::class, 'index'])
                ->name('admin.exchanges.index');
            Route::post('/', [ExchangeController::class, 'store'])
                ->name('admin.exchanges.store');
            Route::post('bulk-delete', [ExchangeController::class, 'bulkDestroy'])
                ->name('admin.exchanges.bulk-delete');
            Route::delete('bulk-delete', [ExchangeController::class, 'bulkDestroy']);
            Route::patch('bulk-status', [ExchangeController::class, 'bulkStatus'])
                ->name('admin.exchanges.bulk-status');
            Route::get('{exchange}', [ExchangeController::class, 'show'])
                ->name('admin.exchanges.show');
            Route::put('{exchange}', [ExchangeController::class, 'update'])
                ->name('admin.exchanges.update');
            Route::delete('{exchange}', [ExchangeController::class, 'destroy'])
                ->name('admin.exchanges.destroy');
            Route::patch('{exchange}/status', [ExchangeController::class, 'updateStatus'])
                ->name('admin.exchanges.update-status');
        });

        // Exchange Commodity Mapping Management
        Route::prefix('exchange-commodity-mappings')->group(function () {
            Route::get('options', [ExchangeCommodityMappingController::class, 'options'])
                ->name('admin.exchange-commodity-mappings.options');
            Route::get('/', [ExchangeCommodityMappingController::class, 'index'])
                ->name('admin.exchange-commodity-mappings.index');
            Route::post('/', [ExchangeCommodityMappingController::class, 'store'])
                ->name('admin.exchange-commodity-mappings.store');
            Route::get('{mapping}', [ExchangeCommodityMappingController::class, 'show'])
                ->name('admin.exchange-commodity-mappings.show');
            Route::put('{mapping}', [ExchangeCommodityMappingController::class, 'update'])
                ->name('admin.exchange-commodity-mappings.update');
            Route::delete('{mapping}', [ExchangeCommodityMappingController::class, 'destroy'])
                ->name('admin.exchange-commodity-mappings.destroy');
            Route::patch('{mapping}/status', [ExchangeCommodityMappingController::class, 'updateStatus'])
                ->name('admin.exchange-commodity-mappings.update-status');
        });

        // Exchange Instrument Management (Manual CRUD, read & local visibility toggle)
        Route::prefix('exchange-instruments')->group(function () {
            Route::get('options', [ExchangeInstrumentController::class, 'options'])
                ->name('admin.exchange-instruments.options');
            Route::get('/', [ExchangeInstrumentController::class, 'index'])
                ->name('admin.exchange-instruments.index');
            Route::post('/', [ExchangeInstrumentController::class, 'store'])
                ->name('admin.exchange-instruments.store');
            Route::get('{instrument}', [ExchangeInstrumentController::class, 'show'])
                ->name('admin.exchange-instruments.show');
            Route::put('{instrument}', [ExchangeInstrumentController::class, 'update'])
                ->name('admin.exchange-instruments.update');
            Route::delete('{instrument}', [ExchangeInstrumentController::class, 'destroy'])
                ->name('admin.exchange-instruments.destroy');
            Route::patch('{instrument}/enabled', [ExchangeInstrumentController::class, 'updateEnabled'])
                ->name('admin.exchange-instruments.update-enabled');
        });

        // Market Ingestion Runs (Operational logs & audit)
        Route::prefix('market-ingestion-runs')->group(function () {
            Route::get('/', [MarketIngestionRunController::class, 'index'])
                ->name('admin.market-ingestion-runs.index');
            Route::get('{run}', [MarketIngestionRunController::class, 'show'])
                ->name('admin.market-ingestion-runs.show');
        });

        // News Sources management
        Route::prefix('news-sources')->group(function () {
            Route::get('options', [AdminNewsSourceController::class, 'options'])
                ->name('admin.news-sources.options');
            Route::get('/', [AdminNewsSourceController::class, 'index'])
                ->name('admin.news-sources.index');
            Route::post('/', [AdminNewsSourceController::class, 'store'])
                ->name('admin.news-sources.store');
            Route::get('{newsSource}', [AdminNewsSourceController::class, 'show'])
                ->name('admin.news-sources.show');
            Route::patch('{newsSource}', [AdminNewsSourceController::class, 'update'])
                ->name('admin.news-sources.update');
            Route::put('{newsSource}', [AdminNewsSourceController::class, 'update']);
            Route::delete('{newsSource}', [AdminNewsSourceController::class, 'destroy'])
                ->name('admin.news-sources.destroy');
            Route::patch('{newsSource}/status', [AdminNewsSourceController::class, 'updateStatus'])
                ->name('admin.news-sources.update-status');
        });

        // News Categories management
        Route::prefix('news-categories')->group(function () {
            Route::get('options', [AdminNewsCategoryController::class, 'options'])
                ->name('admin.news-categories.options');
            Route::get('/', [AdminNewsCategoryController::class, 'index'])
                ->name('admin.news-categories.index');
            Route::post('/', [AdminNewsCategoryController::class, 'store'])
                ->name('admin.news-categories.store');
            Route::get('{newsCategory}', [AdminNewsCategoryController::class, 'show'])
                ->name('admin.news-categories.show');
            Route::patch('{newsCategory}', [AdminNewsCategoryController::class, 'update'])
                ->name('admin.news-categories.update');
            Route::put('{newsCategory}', [AdminNewsCategoryController::class, 'update']);
            Route::delete('{newsCategory}', [AdminNewsCategoryController::class, 'destroy'])
                ->name('admin.news-categories.destroy');
            Route::patch('{newsCategory}/status', [AdminNewsCategoryController::class, 'updateStatus'])
                ->name('admin.news-categories.update-status');
        });

        // News Articles & Media management
        Route::prefix('news')->group(function () {
            Route::get('/', [AdminNewsArticleController::class, 'index'])
                ->name('admin.news.index');
            Route::post('/', [AdminNewsArticleController::class, 'store'])
                ->name('admin.news.store');
            Route::get('{newsArticle}', [AdminNewsArticleController::class, 'show'])
                ->name('admin.news.show');
            Route::patch('{newsArticle}', [AdminNewsArticleController::class, 'update'])
                ->name('admin.news.update');
            Route::put('{newsArticle}', [AdminNewsArticleController::class, 'update']);
            Route::delete('{newsArticle}', [AdminNewsArticleController::class, 'destroy'])
                ->name('admin.news.destroy');

            Route::patch('{newsArticle}/status', [AdminNewsArticleController::class, 'updateStatus'])
                ->name('admin.news.update-status');
            Route::patch('{newsArticle}/featured', [AdminNewsArticleController::class, 'updateFeatured'])
                ->name('admin.news.update-featured');
            Route::patch('{newsArticle}/breaking', [AdminNewsArticleController::class, 'updateBreaking'])
                ->name('admin.news.update-breaking');

            // Article Media
            Route::post('{article}/media', [AdminNewsMediaController::class, 'store'])
                ->name('admin.news.media.store');
            Route::delete('{article}/media/{media}', [AdminNewsMediaController::class, 'destroy'])
                ->name('admin.news.media.destroy');
        });

        // PIB RSS Auto-Import: run history and manual trigger
        Route::prefix('news-import')->group(function () {
            Route::post('runs/trigger', [NewsImportRunController::class, 'trigger'])
                ->name('admin.news-import.runs.trigger');
            Route::get('runs', [NewsImportRunController::class, 'index'])
                ->name('admin.news-import.runs.index');
            Route::get('runs/{run}', [NewsImportRunController::class, 'show'])
                ->name('admin.news-import.runs.show');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
// News Public APIs
Route::prefix('news')->middleware('throttle:public-api')->group(function () {
    Route::get('/', [NewsController::class, 'index'])->name('public.news.index');
    Route::get('{slug}', [NewsController::class, 'show'])->name('public.news.show');
});

Route::get('news-categories', [NewsController::class, 'categories'])
    ->middleware('throttle:public-api')
    ->name('public.news-categories.index');

Route::get('news-sources', [NewsController::class, 'sources'])
    ->middleware('throttle:public-api')
    ->name('public.news-sources.index');

Route::get('site-settings', [SiteSettingController::class, 'show'])
    ->middleware('throttle:public-api')
    ->name('site-settings.show');

Route::get('site-settings/social-links', [SiteSettingController::class, 'socialLinks'])
    ->middleware('throttle:public-api')
    ->name('site-settings.social-links');

Route::get('firebase/config', [PublicFirebaseConfigController::class, 'show'])
    ->middleware('throttle:public-api')
    ->name('firebase.config');

Route::prefix('locations')->middleware('throttle:location-api')->group(function () {
    Route::get('states', [LocationController::class, 'states'])
        ->name('locations.states');
    Route::get('districts', [LocationController::class, 'districts'])
        ->name('locations.districts');
    Route::get('mandis', [LocationController::class, 'mandis'])
        ->name('locations.mandis');
});

Route::prefix('exchanges')->middleware('throttle:public-api')->group(function () {
    Route::get('/', [PublicExchangeController::class, 'index'])
        ->name('public.exchanges.index');
    Route::get('{exchange}/commodities', [PublicExchangeController::class, 'commodities'])
        ->name('public.exchanges.commodities');
    Route::get('{exchange}/instruments', [PublicExchangeController::class, 'instruments'])
        ->name('public.exchanges.instruments');
});

Route::get('exchange-instruments/{instrument}', [PublicExchangeController::class, 'showInstrument'])
    ->middleware('throttle:public-api')
    ->name('public.exchange-instruments.show');

Route::prefix('markets')->middleware('throttle:public-api')->group(function () {
    Route::get('instruments/{instrument}/history', [MarketHistoryController::class, 'instrumentHistory'])
        ->name('public.markets.instruments.history');
});

/*
|--------------------------------------------------------------------------
| Authenticated Push Notification Device Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'user'])->prefix('notifications/devices')->group(function () {
    Route::post('/', [NotificationDeviceController::class, 'store'])
        ->name('notifications.devices.store');
    Route::delete('/', [NotificationDeviceController::class, 'destroy'])
        ->name('notifications.devices.destroy');
});

/*
|--------------------------------------------------------------------------
| Authenticated User In-App Notification Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'user'])->prefix('notifications')->group(function () {
    Route::get('/', [InAppNotificationController::class, 'index'])
        ->name('notifications.index');
    Route::get('unread-count', [InAppNotificationController::class, 'unreadCount'])
        ->name('notifications.unread-count');
    Route::patch('read-all', [InAppNotificationController::class, 'markAllAsRead'])
        ->name('notifications.read-all');
    Route::patch('{id}/read', [InAppNotificationController::class, 'markAsRead'])
        ->name('notifications.read');
    Route::delete('read', [InAppNotificationController::class, 'clearRead'])
        ->name('notifications.clear-read');
    Route::delete('/', [InAppNotificationController::class, 'clearRead']);
    Route::delete('{id}', [InAppNotificationController::class, 'destroy'])
        ->name('notifications.destroy');
});

/*
|--------------------------------------------------------------------------
| User API Routes
|--------------------------------------------------------------------------
*/
Route::prefix('user')->group(function () {
    // OTP Management
    Route::post('send-otp', [UserAuthController::class, 'sendOtp'])
        ->middleware('throttle:user-send-otp')
        ->name('user.send-otp');
    Route::post('send-login-otp', [UserAuthController::class, 'sendLoginOtp'])
        ->middleware('throttle:user-send-otp')
        ->name('user.send-login-otp');
    Route::post('otp/send', [UserAuthController::class, 'sendOtp'])
        ->middleware('throttle:user-send-otp')
        ->name('user.otp.send');

    Route::post('verify-otp', [UserAuthController::class, 'verifyOtp'])
        ->middleware('throttle:user-verify-otp')
        ->name('user.verify-otp');
    Route::post('otp/verify', [UserAuthController::class, 'verifyOtp'])
        ->middleware('throttle:user-verify-otp')
        ->name('user.otp.verify');

    // Real-time username validation & availability
    Route::post('validate-username', [UserAuthController::class, 'validateUsername'])
        ->middleware('throttle:user-api')
        ->name('user.validate-username');

    // Registration
    Route::post('register', [UserAuthController::class, 'register'])
        ->middleware('throttle:user-register')
        ->name('user.register');

    // Public user authentication & password recovery
    Route::post('login', [UserAuthController::class, 'login'])
        ->middleware('throttle:user-login')
        ->name('user.login');

    Route::post('login-with-otp', [UserAuthController::class, 'loginWithOtp'])
        ->middleware('throttle:user-login')
        ->name('user.login-with-otp');

    Route::post('forgot-password', [UserAuthController::class, 'forgotPassword'])
        ->middleware('throttle:user-password-reset')
        ->name('user.forgot-password');

    Route::post('reset-password', [UserAuthController::class, 'resetPassword'])
        ->middleware('throttle:user-password-reset')
        ->name('user.reset-password');

    Route::match(['get', 'post'], 'verify-reset-token', [UserAuthController::class, 'verifyResetToken'])
        ->middleware('throttle:user-password-reset')
        ->name('user.verify-reset-token');

    // Protected user endpoints
    Route::middleware(['auth:sanctum', 'user'])->group(function () {
        // Token management
        Route::post('refresh-token', [UserAuthController::class, 'refreshToken'])
            ->name('user.refresh-token');

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

        // User activities & login history
        Route::get('activities', [UserActivityController::class, 'index'])
            ->name('user.activities.index');
        Route::get('logins', [UserActivityController::class, 'logins'])
            ->name('user.logins.index');

        // In-app notifications
        Route::prefix('notifications')->group(function () {
            Route::get('/', [InAppNotificationController::class, 'index'])
                ->name('user.notifications.index');
            Route::get('unread-count', [InAppNotificationController::class, 'unreadCount'])
                ->name('user.notifications.unread-count');
            Route::patch('read-all', [InAppNotificationController::class, 'markAllAsRead'])
                ->name('user.notifications.read-all');
            Route::patch('{id}/read', [InAppNotificationController::class, 'markAsRead'])
                ->name('user.notifications.read');
            Route::delete('read', [InAppNotificationController::class, 'clearRead'])
                ->name('user.notifications.clear-read');
            Route::delete('/', [InAppNotificationController::class, 'clearRead']);
            Route::delete('{id}', [InAppNotificationController::class, 'destroy'])
                ->name('user.notifications.destroy');
        });

        // Push notification devices
        Route::prefix('notifications/devices')->group(function () {
            Route::post('/', [NotificationDeviceController::class, 'store'])
                ->name('user.notifications.devices.store');
            Route::delete('/', [NotificationDeviceController::class, 'destroy'])
                ->name('user.notifications.devices.destroy');
        });

        // Trader Company Profile
        Route::prefix('company')->group(function () {
            Route::get('/', [UserCompanyController::class, 'show'])
                ->name('user.company.show');
            Route::post('/', [UserCompanyController::class, 'store'])
                ->name('user.company.store');
            Route::put('/', [UserCompanyController::class, 'update'])
                ->name('user.company.update');
            Route::patch('/', [UserCompanyController::class, 'update']);
        });
    });
});
