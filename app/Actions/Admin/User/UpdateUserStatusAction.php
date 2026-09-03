<?php

namespace App\Actions\Admin\User;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateUserStatusAction
{
    /**
     * Update user status and revoke all tokens if inactive or suspended.
     *
     * @param  User  $user
     * @param  string  $status
     * @return User
     */
    public function execute(User $user, string $status): User
    {
        return DB::transaction(function () use ($user, $status) {
            $user->update([
                'status' => $status,
            ]);

            // If user is deactivated or suspended, revoke all existing tokens immediately
            if (in_array($status, ['inactive', 'suspended'], true)) {
                $user->tokens()->delete();
            }

            return $user->fresh();
        });
    }
}
