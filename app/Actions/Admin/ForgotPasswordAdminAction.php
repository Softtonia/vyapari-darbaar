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

        // Only send reset notification if Admin exists and is active
        if ($admin && $admin->status === 'active') {
            Password::broker('admins')->sendResetLink(['email' => $normalizedEmail]);
        }

        // Always return the exact same generic enumeration-safe response
        return [
            'status' => true,
            'message' => 'If an account exists for this email, a password reset link has been sent.',
            'data' => (object) [],
        ];
    }
}
