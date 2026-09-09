<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class ResetPasswordUserAction
{
    /**
     * Reset the user's password and revoke all active Sanctum tokens.
     *
     * @param  array{email: string, token: string, password: string, password_confirmation: string}  $credentials
     * @return array{success: bool, message: string, code: int}
     */
    public function execute(array $credentials): array
    {
        $normalizedEmail = strtolower(trim($credentials['email']));

        $user = User::query()
            ->where('email', $normalizedEmail)
            ->first();

        if (! $user) {
            return [
                'success' => false,
                'message' => 'No user account found with this email address.',
                'code' => 404,
            ];
        }

        if ($user->status !== 'active') {
            return [
                'success' => false,
                'message' => 'Account is inactive. Please contact customer support.',
                'code' => 403,
            ];
        }

        $credentials['email'] = $normalizedEmail;

        $status = DB::transaction(function () use ($credentials) {
            return Password::broker('users')->reset(
                $credentials,
                function (User $user, string $password) {
                    $user->forceFill([
                        'password' => Hash::make($password),
                        'must_change_password' => false,
                    ])->save();

                    // Security: Revoke all existing Sanctum sessions
                    $user->tokens()->delete();
                }
            );
        });

        if ($status === Password::PASSWORD_RESET) {
            // Security: Ensure all active login tokens across all devices are deleted
            $user->tokens()->delete();

            \App\Services\UserActivityService::log(
                $user,
                'password_reset',
                'User reset account password'
            );

            return [
                'success' => true,
                'message' => 'Your password has been reset successfully.',
                'code' => 200,
            ];
        }

        if ($status === Password::INVALID_USER) {
            return [
                'success' => false,
                'message' => 'No user account found with this email address.',
                'code' => 404,
            ];
        }

        return [
            'success' => false,
            'message' => 'This password reset token is invalid or has expired.',
            'code' => 400,
        ];
    }
}
