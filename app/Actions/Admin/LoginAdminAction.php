<?php

namespace App\Actions\Admin;

use App\Models\Admin;
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

        $admin = Admin::query()
            ->select(['id', 'first_name', 'last_name', 'name', 'email', 'password', 'status', 'last_login_at'])
            ->where('email', $email)
            ->first();

        if (! $admin) {
            return [
                'success' => false,
                'message' => 'No account found with this email address.',
                'code' => 401,
            ];
        }

        if (! Hash::check($password, $admin->password)) {
            return [
                'success' => false,
                'message' => 'Incorrect password.',
                'code' => 401,
            ];
        }

        if ($admin->status !== 'active') {
            return [
                'success' => false,
                'message' => 'Account is inactive. Please contact the administrator.',
                'code' => 403,
            ];
        }

        $admin->update([
            'last_login_at' => now(),
        ]);

        // Check if an unexpired active token exists for this admin
        $activeToken = DB::table('personal_access_tokens')
            ->where('tokenable_type', $admin->getMorphClass())
            ->where('tokenable_id', $admin->getKey())
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
                ],
            ];
        }

        $expiresMinutes = (int) (config('sanctum.expiration') ?? 1440);
        $tokenResult = $admin->createToken($tokenName, ['*'], now()->addMinutes($expiresMinutes));

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
            ],
        ];
    }
}
