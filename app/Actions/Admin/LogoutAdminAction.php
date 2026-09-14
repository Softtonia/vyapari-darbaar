<?php

namespace App\Actions\Admin;

use App\Models\User;

class LogoutAdminAction
{
    /**
     * Delete the current access token for the authenticated admin.
     *
     * @param  User  $admin
     * @return array{status: bool, message: string, data: array<string, mixed>}
     */
    public function execute(User $admin): array
    {
        $admin->currentAccessToken()?->delete();

        return [
            'status' => true,
            'message' => 'Logged out successfully.',
        ];
    }
}
