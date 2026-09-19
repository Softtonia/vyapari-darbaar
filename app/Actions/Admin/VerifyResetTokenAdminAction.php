<?php

namespace App\Actions\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Password;

class VerifyResetTokenAdminAction
{
    /**
     * Check if a password reset token is valid and not expired for the given admin email.
     *
     * @return array{success: bool, message: string, code: int}
     */
    public function execute(string $email, string $token): array
    {
        $normalizedEmail = strtolower(trim($email));

        $admin = User::query()
            ->where('email', $normalizedEmail)
            ->first();

        if (! $admin) {
            return [
                'success' => false,
                'message' => 'No administrator account found with this email address.',
                'code' => 404,
            ];
        }

        $hasAccess = (bool) $admin->is_default
            || $admin->hasAnyRole(['super_admin', 'admin', 'editor'])
            || $admin->getAllPermissions()->isNotEmpty();

        if (! $hasAccess) {
            return [
                'success' => false,
                'message' => 'No administrator account found with this email address.',
                'code' => 404,
            ];
        }

        if ($admin->status !== 'active') {
            return [
                'success' => false,
                'message' => 'Account is inactive. Please contact the administrator.',
                'code' => 403,
            ];
        }

        if (! Password::broker('admins')->tokenExists($admin, $token)) {
            return [
                'success' => false,
                'message' => 'This password reset link is invalid or has expired.',
                'code' => 400,
            ];
        }

        return [
            'success' => true,
            'message' => 'Password reset token is valid.',
            'code' => 200,
        ];
    }
}
