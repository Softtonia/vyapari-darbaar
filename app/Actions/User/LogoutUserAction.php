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
        $user->currentAccessToken()?->delete();
    }
}
