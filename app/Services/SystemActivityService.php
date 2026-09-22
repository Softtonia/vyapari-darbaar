<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class SystemActivityService
{
    /**
     * Log a system activity / audit log entry.
     *
     * @param  string  $module       e.g. 'Website', 'News', 'Users', 'Auth', 'Mandis', 'Commodities'
     * @param  string  $action       e.g. 'Created', 'Updated', 'Deleted', 'Login', 'Status Changed'
     * @param  string  $description  Human-readable description of the activity
     * @param  User|null  $user      Performer user instance or null for auto-detect / system
     * @param  array<string, mixed>  $properties  Additional metadata / before-after diffs
     * @param  string  $status       'Success' or 'Failed'
     * @param  Request|null  $request Optional HTTP request context
     * @param  string|null  $event   Internal event code or slug
     * @return UserActivity|null
     */
    public static function log(
        string $module,
        string $action,
        string $description,
        ?User $user = null,
        array $properties = [],
        string $status = 'Success',
        ?Request $request = null,
        ?string $event = null
    ): ?UserActivity {
        try {
            $req = $request ?? (request() instanceof Request ? request() : null);

            $performer = $user;
            if ($performer === null && Auth::check()) {
                $authUser = Auth::user();
                if ($authUser instanceof User) {
                    $performer = $authUser;
                }
            }

            $ip = null;
            $userAgent = null;
            if ($req) {
                $ip = $req->ip();
                $userAgent = substr((string) $req->userAgent(), 0, 500);
            }

            $eventCode = $event ?? strtolower(str_replace(' ', '_', "{$module}.{$action}"));

            return UserActivity::create([
                'user_id' => $performer?->id,
                'module' => $module,
                'action' => $action,
                'event' => $eventCode,
                'description' => $description,
                'status' => $status,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'properties' => $properties,
            ]);
        } catch (Throwable) {
            // Failsafe: Logging must never crash the primary business operation
            return null;
        }
    }
}
