<?php

namespace App\Actions\Admin;

use App\Models\Admin;
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
            ->select(['id', 'name', 'email', 'password', 'status', 'last_login_at'])
            ->where('email', $email)
            ->first();

        if (! $admin || ! Hash::check($password, $admin->password)) {
            return [
                'success' => false,
                'message' => 'Invalid credentials.',
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

        $tokenResult = $admin->createToken($tokenName);

        return [
            'success' => true,
            'message' => 'Login successful.',
            'code' => 200,
            'data' => [
                'token' => $tokenResult->plainTextToken,
                'token_type' => 'Bearer',
                'admin' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'status' => $admin->status,
                ],
            ],
        ];
    }
}
