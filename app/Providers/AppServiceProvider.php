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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureMailSynchronization();
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
            $email = strtolower(trim((string) $request->input('email', '')));
            $identifier = sha1($email.'|'.$request->ip());

            return Limit::perMinute(5)
                ->by($identifier)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Too many login attempts. Please try again later.',
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
            $key = ($user instanceof Admin)
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

        RateLimiter::for('admin-user-create', function (Request $request) {
            $user = $request->user();
            $key = ($user instanceof Admin)
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

            $key = ($admin instanceof Admin)
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
            $key = ($user instanceof Admin)
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
    }
}
