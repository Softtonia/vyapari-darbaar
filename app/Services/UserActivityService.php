<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Http\Request;

class UserActivityService
{
    /**
     * Log a user activity event.
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
        $req = $request ?? request();

        return UserActivity::create([
            'user_id' => $user?->id,
            'event' => $event,
            'description' => $description,
            'ip_address' => $req ? $req->ip() : null,
            'user_agent' => $req ? substr((string) $req->userAgent(), 0, 500) : null,
            'properties' => $properties,
        ]);
    }
}
