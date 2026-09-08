<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\Password;

class ForgotPasswordUserAction
{
    /**
     * Send a password reset link to the given user email if active and eligible.
     *
     * @param  string  $email
     * @return array{status: bool, message: string, code: int}
     */
    public function execute(string $email): array
    {
        $normalizedEmail = strtolower(trim($email));

        $user = User::query()
            ->where('email', $normalizedEmail)
            ->first();

        if (! $user) {
            return [
                'status' => false,
                'message' => 'No user account found with this email address.',
                'code' => 404,
            ];
        }

        if ($user->status !== 'active') {
            return [
                'status' => false,
                'message' => 'Account is inactive. Please contact customer support.',
                'code' => 403,
            ];
        }

        $status = Password::broker('users')->sendResetLink(['email' => $normalizedEmail]);

        if ($status === Password::RESET_THROTTLED) {
            return [
                'status' => false,
                'message' => 'A password reset link was already sent recently. Please check your email or wait a moment before requesting again.',
                'code' => 429,
            ];
        }

        if ($status === Password::RESET_LINK_SENT) {
            return [
                'status' => true,
                'message' => 'A password reset link has been sent to your email address.',
                'code' => 200,
            ];
        }

        return [
            'status' => false,
            'message' => 'Unable to send password reset link. Please try again.',
            'code' => 500,
        ];
    }
}
