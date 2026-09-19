<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\Password;

class VerifyResetTokenUserAction
{
    /**
     * Check if a password reset token is valid and not expired for the given user email.
     *
     * @return array{success: bool, message: string, code: int}
     */
    public function execute(string $email, string $token): array
    {
        $normalizedEmail = strtolower(trim($email));

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

        if (! Password::broker('users')->tokenExists($user, $token)) {
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
