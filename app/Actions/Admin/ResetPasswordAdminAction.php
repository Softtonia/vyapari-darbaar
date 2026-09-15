<?php

namespace App\Actions\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class ResetPasswordAdminAction
{
    /**
     * Reset the admin's password and revoke all active Sanctum tokens.
     *
     * @param  array{email: string, token: string, password: string, password_confirmation: string}  $credentials
     * @return array{success: bool, message: string, error?: string, data?: array<string, mixed>, code: int}
     */
    public function execute(array $credentials): array
    {
        $normalizedEmail = strtolower(trim($credentials['email']));

        $admin = User::query()
            ->where('email', $normalizedEmail)
            ->first();

        if (! $admin) {
            return [
                'success' => false,
                'message' => 'No administrator account found with this email address.',
                'error' => 'No administrator account found with this email address.',
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
                'error' => 'No administrator account found with this email address.',
                'code' => 404,
            ];
        }

        if ($admin->status !== 'active') {
            return [
                'success' => false,
                'message' => 'Account is inactive. Please contact the administrator.',
                'error' => 'Account is inactive. Please contact the administrator.',
                'code' => 403,
            ];
        }

        $credentials['email'] = $normalizedEmail;

        $status = DB::transaction(function () use ($credentials) {
            return Password::broker('admins')->reset(
                $credentials,
                function (User $admin, string $password) {
                    $admin->forceFill([
                        'password' => Hash::make($password),
                    ])->save();

                    // Security requirement: Revoke all existing Sanctum sessions
                    $admin->tokens()->delete();
                }
            );
        });

        if ($status === Password::PASSWORD_RESET) {
            // Security: Revoke all active login tokens across all devices
            $admin->tokens()->delete();

            return [
                'success' => true,
                'message' => 'Your password has been reset successfully.',
                'code' => 200,
            ];
        }

        if ($status === Password::INVALID_USER) {
            return [
                'success' => false,
                'message' => 'No administrator account found with this email address.',
                'error' => 'No administrator account found with this email address.',
                'code' => 404,
            ];
        }

        return [
            'success' => false,
            'message' => 'This password reset token is invalid or has expired.',
            'error' => 'Invalid password reset token.',
            'code' => 400,
        ];
    }
}
