<?php

namespace App\Actions\Admin;

use App\Models\Admin;
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
        $status = DB::transaction(function () use ($credentials) {
            return Password::broker('admins')->reset(
                $credentials,
                function (Admin $admin, string $password) {
                    $admin->forceFill([
                        'password' => Hash::make($password),
                    ])->save();

                    // Security requirement: Revoke all existing Sanctum sessions
                    $admin->tokens()->delete();
                }
            );
        });

        if ($status === Password::PASSWORD_RESET) {
            return [
                'success' => true,
                'message' => 'Your password has been reset successfully.',
                'code' => 200,
                'data' => (object) [],
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
