<?php

namespace App\Actions\Admin;

use App\Models\User;

class LogoutAllAdminSessionsAction
{
    /**
     * Delete all personal access tokens for the authenticated admin.
     *
     * @param  User  $admin
     * @return array{status: bool, message: string, data: array<string, mixed>}
     */
    public function execute(User $admin): array
    {
        $admin->tokens()->delete();

        return [
            'status' => true,
            'message' => 'Logged out from all devices successfully.',
        ];
    }
}
