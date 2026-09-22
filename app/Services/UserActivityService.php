<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Http\Request;

class UserActivityService
{
    /**
     * Log a user activity event with backward compatibility.
     *
     * @param  User|null  $user
     * @param  string  $event
     * @param  string  $description
     * @param  array<string, mixed>  $properties
     * @param  Request|null  $request
     * @return UserActivity
     */
    public static function log(
        ?User $user,
        string $event,
        string $description,
        array $properties = [],
        ?Request $request = null
    ): UserActivity {
        $req = $request ?? (request() instanceof Request ? request() : null);

        // Derive module and action from event
        $module = 'Users';
        $action = 'Updated';
        $status = 'Success';

        if (str_contains($event, 'login_failed') || str_contains($event, 'failed')) {
            $status = 'Failed';
        }

        if (str_contains($event, 'login') || str_contains($event, 'logout') || str_contains($event, 'token') || str_contains($event, 'otp') || str_contains($event, 'password')) {
            $module = 'Auth';
            if (str_contains($event, 'login')) {
                $action = 'Login';
            } elseif (str_contains($event, 'logout')) {
                $action = 'Logout';
            } else {
                $action = 'Updated';
            }
        } elseif (str_contains($event, 'company')) {
            $module = 'Users';
            $action = 'Updated';
        } elseif (str_contains($event, 'notification')) {
            $module = 'Users';
            $action = 'Updated';
        }

        return UserActivity::create([
            'user_id' => $user?->id,
            'module' => $module,
            'action' => $action,
            'event' => $event,
            'description' => $description,
            'status' => $status,
            'ip_address' => $req ? $req->ip() : null,
            'user_agent' => $req ? substr((string) $req->userAgent(), 0, 500) : null,
            'properties' => $properties,
        ]);
    }
}
