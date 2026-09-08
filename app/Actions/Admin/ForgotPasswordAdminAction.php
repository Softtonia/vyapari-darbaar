<?php

namespace App\Actions\Admin;

use App\Models\Admin;
use Illuminate\Support\Facades\Password;

class ForgotPasswordAdminAction
{
    /**
     * Send a password reset link to the given administrator email if active and eligible.
     *
     * @param  string  $email
     * @return array{status: bool, message: string, data: array<string, mixed>}
     */
    public function execute(string $email): array
    {
        $normalizedEmail = strtolower(trim($email));

        $admin = Admin::query()
            ->where('email', $normalizedEmail)
            ->first();

        if (! $admin) {
            return [
                'status' => false,
                'message' => 'No administrator account found with this email address.',
                'code' => 404,
            ];
        }

        if ($admin->status !== 'active') {
            return [
                'status' => false,
                'message' => 'Account is inactive. Please contact the administrator.',
                'code' => 403,
            ];
        }

        $status = Password::broker('admins')->sendResetLink(['email' => $normalizedEmail]);

        if ($status === Password::RESET_THROTTLED) {
            return [
                'status' => false,
                'message' => 'A password reset link was already sent recently. Please check your email or wait 10 minutes before requesting again.',
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
