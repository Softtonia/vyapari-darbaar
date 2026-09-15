<?php

namespace App\Actions\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LoginAdminAction
{
    /**
     * Execute the admin login action.
     *
     * @param  array{email: string, password: string}  $credentials
     * @param  string  $tokenName
     * @return array{success: bool, message: string, data?: array<string, mixed>, code: int}
     */
    public function execute(array $credentials, string $tokenName = 'admin-token'): array
    {
        $email = strtolower(trim($credentials['email']));
        $password = $credentials['password'];

        $user = User::query()
            ->with('roles')
            ->where('email', $email)
            ->first();

        if (! $user) {
            return [
                'success' => false,
                'message' => 'No account found with this email address.',
                'code' => 401,
            ];
        }

        if (! Hash::check($password, $user->password)) {
            \App\Services\UserActivityService::log(
                $user,
                'login_failed',
                "Failed admin login attempt: incorrect password from device '{$tokenName}'",
                ['device_name' => $tokenName, 'reason' => 'incorrect_password']
            );

            return [
                'success' => false,
                'message' => 'Incorrect password.',
                'code' => 401,
            ];
        }

        // Verify that account has administrative privileges
        $hasAccess = (bool) $user->is_default
            || $user->hasAnyRole(['super_admin', 'admin', 'editor'])
            || $user->getAllPermissions()->isNotEmpty();

        if (! $hasAccess) {
            \App\Services\UserActivityService::log(
                $user,
                'login_failed',
                "Failed admin login attempt: unauthorized administrative access from device '{$tokenName}'",
                ['device_name' => $tokenName, 'reason' => 'unauthorized_access']
            );

            return [
                'success' => false,
                'message' => 'Unauthorized access.',
                'code' => 403,
            ];
        }

        if ($user->status !== 'active') {
            \App\Services\UserActivityService::log(
                $user,
                'login_failed',
                "Failed admin login attempt: account inactive from device '{$tokenName}'",
                ['device_name' => $tokenName, 'reason' => 'account_inactive']
            );

            return [
                'success' => false,
                'message' => 'Account is inactive. Please contact the administrator.',
                'code' => 403,
            ];
        }

        $user->update([
            'last_login_at' => now(),
        ]);

        $roleName = $user->roles->first()?->name ?? ($user->is_default ? 'super_admin' : 'admin');

        \App\Services\UserActivityService::log(
            $user,
            'login',
            "Admin logged in from device '{$tokenName}'",
            ['device_name' => $tokenName, 'role' => $roleName]
        );

        // Check if an unexpired active token exists for this admin
        $activeToken = DB::table('personal_access_tokens')
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->where('name', $tokenName)
            ->where('expires_at', '>', now())
            ->whereNotNull('plain_token')
            ->latest('id')
            ->first();

        if ($activeToken && ! empty($activeToken->plain_token)) {
            return [
                'success' => true,
                'message' => 'Login successful.',
                'code' => 200,
                'data' => [
                    'token' => $activeToken->plain_token,
                    'role' => $roleName,
                ],
            ];
        }

        $expiresMinutes = (int) (config('sanctum.expiration') ?? 1440);
        $tokenResult = $user->createToken($tokenName, ['*'], now()->addMinutes($expiresMinutes));

        DB::table('personal_access_tokens')
            ->where('id', $tokenResult->accessToken->id)
            ->update([
                'plain_token' => $tokenResult->plainTextToken,
            ]);

        return [
            'success' => true,
            'message' => 'Login successful.',
            'code' => 200,
            'data' => [
                'token' => $tokenResult->plainTextToken,
                'role' => $roleName,
            ],
        ];
    }
}
