<?php

namespace App\Actions\User;

use App\Models\User;

class LogoutUserAction
{
    /**
     * Revoke the current access token.
     *
     * @param  User  $user
     * @return void
     */
    public function execute(User $user): void
    {
        \App\Services\UserActivityService::log(
            $user,
            'logout',
            'User logged out'
        );

        $user->currentAccessToken()?->delete();
    }
}
