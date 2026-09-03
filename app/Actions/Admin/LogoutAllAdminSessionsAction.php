<?php

namespace App\Actions\Admin;

use App\Models\Admin;

class LogoutAllAdminSessionsAction
{
    /**
     * Delete all personal access tokens for the authenticated admin.
     *
     * @param  Admin  $admin
     * @return array{status: bool, message: string, data: array<string, mixed>}
     */
    public function execute(Admin $admin): array
    {
        $admin->tokens()->delete();

        return [
            'status' => true,
            'message' => 'Logged out from all devices successfully.',
            'data' => (object) [],
        ];
    }
}
