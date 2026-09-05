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
                'data' => (object) [],
            ];
        }

        if ($admin->status !== 'active') {
            return [
                'status' => false,
                'message' => 'Account is inactive. Please contact the administrator.',
                'code' => 403,
                'data' => (object) [],
            ];
        }

        Password::broker('admins')->sendResetLink(['email' => $normalizedEmail]);

        return [
            'status' => true,
            'message' => 'A password reset link has been sent to your email address.',
            'code' => 200,
            'data' => (object) [],
        ];
    }
}
