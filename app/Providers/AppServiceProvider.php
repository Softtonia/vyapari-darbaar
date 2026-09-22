<?php

namespace App\Providers;

use App\Models\Admin;
use App\Services\DynamicMailConfigService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DynamicMailConfigService::class, function () {
            return new DynamicMailConfigService;
        });

        $this->app->singleton(\App\Services\Firebase\FirebaseConfigService::class, function () {
            return new \App\Services\Firebase\FirebaseConfigService;
        });

        $this->app->singleton(
            \App\Services\Firebase\Contracts\FirebaseAccessTokenProvider::class,
            \App\Services\Firebase\GoogleFirebaseAccessTokenProvider::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureMailSynchronization();
        $this->registerActivityObservers();
    }

    /**
     * Register real-time activity log observers across all primary models.
     */
    protected function registerActivityObservers(): void
    {
        foreach (array_keys(\App\Observers\ActivityLogObserver::MODULE_MAP) as $modelClass) {
            if (class_exists($modelClass)) {
                $modelClass::observe(\App\Observers\ActivityLogObserver::class);
            }
        }
    }

    /**
     * Configure dynamic SMTP queue synchronization and boot initialization.
     */
    protected function configureMailSynchronization(): void
    {
        try {
            app(DynamicMailConfigService::class)->apply();
        } catch (\Throwable) {
            // Failsafe during setup/migrations before table exists
        }

        Queue::before(function (JobProcessing $event) {
            if ($event->job->getQueue() === 'emails') {
                app(DynamicMailConfigService::class)->apply();
            }
        });
    }

    /**
     * Configure named rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('admin-login', function (Request $request) {
            $rawIdentifier = $request->input('email')
                ?? $request->input('mobile')
                ?? $request->input('phone')
                ?? $request->input('phone_number')
                ?? $request->input('username')
                ?? $request->input('identifier', '');
            $identifier = sha1(strtolower(trim((string) $rawIdentifier)).'|'.$request->ip());

            return Limit::perMinute(5)
                ->by($identifier)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Too many login attempts. Please try again later.',
                    ], 429, $headers);
                });
        });

        RateLimiter::for('admin-send-otp', function (Request $request) {
            $rawIdentifier = $request->input('email')
                ?? $request->input('mobile')
                ?? $request->input('phone')
                ?? $request->input('phone_number')
                ?? $request->input('username')
                ?? $request->input('identifier', '');
            $identifier = sha1(strtolower(trim((string) $rawIdentifier)).'|'.$request->ip());

            return Limit::perMinute(5)
                ->by('admin-send-otp:'.$identifier)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Too many OTP requests. Please try again later.',
                    ], 429, $headers);
                });
        });

        RateLimiter::for('admin-password-reset', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email', '')));
            $identifier = sha1($email.'|'.$request->ip());

            return Limit::perMinute(5)
                ->by('admin-password-reset:'.$identifier)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Too many password reset attempts. Please try again later.',
                    ], 429, $headers);
                });
        });

        RateLimiter::for('admin-smtp-test', function (Request $request) {
            $user = $request->user();
            $key = ($user instanceof \App\Models\User)
                ? 'admin-smtp-test:'.$user->id
                : 'guest:'.$request->ip();

            return Limit::perMinute(5)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Too many SMTP test attempts. Please try again later.',
                    ], 429, $headers);
                });
        });

        RateLimiter::for('admin-firebase-test', function (Request $request) {
            $user = $request->user();
            $key = ($user instanceof \App\Models\User)
                ? 'admin-firebase-test:'.$user->id
                : 'guest:'.$request->ip();

            return Limit::perMinute(5)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Too many Firebase test attempts. Please try again later.',
                    ], 429, $headers);
                });
        });

        RateLimiter::for('admin-notification-send', function (Request $request) {
            $user = $request->user();
            $key = ($user instanceof \App\Models\User)
                ? 'admin-notification-send:'.$user->id
                : 'guest:'.$request->ip();

            return Limit::perMinute(10)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Too many notification send requests. Please try again later.',
                    ], 429, $headers);
                });
        });

        RateLimiter::for('admin-notification-preview', function (Request $request) {
            $user = $request->user();
            $key = ($user instanceof \App\Models\User)
                ? 'admin-notification-preview:'.$user->id
                : 'guest:'.$request->ip();

            return Limit::perMinute(30)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Too many notification preview requests. Please try again later.',
                    ], 429, $headers);
                });
        });

        RateLimiter::for('admin-user-create', function (Request $request) {
            $user = $request->user();
            $key = ($user instanceof \App\Models\User)
                ? 'admin-user-create:'.$user->id
                : 'guest:'.$request->ip();

            return Limit::perMinute(20)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Too many user creation requests. Please try again later.',
                    ], 429, $headers);
                });
        });

        RateLimiter::for('admin-user-resend-credentials', function (Request $request) {
            $admin = $request->user();
            $targetUser = $request->route('user');
            $targetUserId = $targetUser instanceof \App\Models\User ? $targetUser->id : (string) $request->route('user');

            $key = ($admin instanceof \App\Models\User)
                ? 'admin-user-resend:'.$admin->id.':user:'.$targetUserId
                : 'guest:'.$request->ip();

            return Limit::perMinutes(10, 3)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Too many credential resend requests for this user. Please try again later.',
                    ], 429, $headers);
                });
        });

        RateLimiter::for('admin-api', function (Request $request) {
            $user = $request->user();
            $key = ($user instanceof \App\Models\User)
                ? 'admin:'.$user->id
                : 'guest:'.$request->ip();

            return Limit::perMinute(60)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Too many requests. Please slow down.',
                    ], 429, $headers);
                });
        });

        RateLimiter::for('user-login', function (Request $request) {
            $username = strtolower(trim((string) $request->input('username', '')));
            $identifier = sha1($username.'|'.$request->ip());

            return Limit::perMinute(5)
                ->by('user-login:'.$identifier)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Too many login attempts. Please try again later.',
                    ], 429, $headers);
                });
        });

        RateLimiter::for('user-password-reset', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email', '')));
            $identifier = sha1($email.'|'.$request->ip());

            return Limit::perMinute(5)
                ->by('user-password-reset:'.$identifier)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Too many password reset attempts. Please try again later.',
                    ], 429, $headers);
                });
        });

        RateLimiter::for('user-change-password', function (Request $request) {
            $user = $request->user();
            $key = ($user instanceof \App\Models\User)
                ? 'user-change-password:'.$user->id
                : 'guest:'.$request->ip();

            return Limit::perMinute(5)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Too many password change attempts. Please try again later.',
                    ], 429, $headers);
                });
        });

        RateLimiter::for('user-send-otp', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email', '')));
            $identifier = sha1($email.'|'.$request->ip());

            return Limit::perMinute(6)
                ->by('user-send-otp:'.$identifier)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Too many OTP requests. Please try again later.',
                    ], 429, $headers);
                });
        });

        RateLimiter::for('user-verify-otp', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email', '')));
            $identifier = sha1($email.'|'.$request->ip());

            return Limit::perMinute(10)
                ->by('user-verify-otp:'.$identifier)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Too many OTP verification attempts. Please try again later.',
                    ], 429, $headers);
                });
        });

        RateLimiter::for('user-register', function (Request $request) {
            return Limit::perMinute(10)
                ->by('user-register:'.$request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Too many registration requests. Please try again later.',
                    ], 429, $headers);
                });
        });

        RateLimiter::for('user-api', function (Request $request) {
            $user = $request->user();
            $key = ($user instanceof \App\Models\User)
                ? 'user:'.$user->id
                : 'guest:'.$request->ip();

            return Limit::perMinute(120)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Too many requests. Please slow down.',
                    ], 429, $headers);
                });
        });

        RateLimiter::for('public-api', function (Request $request) {
            return Limit::perMinute(60)
                ->by('public:'.$request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Too many requests. Please slow down.',
                    ], 429, $headers);
                });
        });

        RateLimiter::for('location-api', function (Request $request) {
            return Limit::perMinute(100)
                ->by('location:'.$request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Too many location requests. Please slow down.',
                    ], 429, $headers);
                });
        });
    }
}
