<?php

namespace App\Actions\Admin;

use App\Models\Admin;

class LogoutAdminAction
{
    /**
     * Delete the current access token for the authenticated admin.
     *
     * @param  Admin  $admin
     * @return array{status: bool, message: string, data: array<string, mixed>}
     */
    public function execute(Admin $admin): array
    {
        $admin->currentAccessToken()?->delete();

        return [
            'status' => true,
            'message' => 'Logged out successfully.',
        ];
    }
}
