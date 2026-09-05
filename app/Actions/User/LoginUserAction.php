<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LoginUserAction
{
    /**
     * Authenticate a user by username and issue a Sanctum token.
     *
     * @param  array{username: string, password: string}  $credentials
     * @param  string  $deviceName
     * @return array{success: true, token: string, user: User}|array{success: false, message: string, code: int}
     */
    public function execute(array $credentials, string $deviceName = 'user-device'): array
    {
        $user = User::query()
            ->select([
                'id',
                'name',
                'username',
                'email',
                'password',
                'status',
                'must_change_password',
            ])
            ->where('username', $credentials['username'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return [
                'success' => false,
                'message' => 'Invalid credentials.',
                'code' => 401,
            ];
        }

        if ($user->status !== 'active') {
            return [
                'success' => false,
                'message' => 'Invalid credentials.',
                'code' => 401,
            ];
        }

        $expiresMinutes = (int) (config('sanctum.expiration') ?? 1440);
        $token = $user->createToken($deviceName, ['*'], now()->addMinutes($expiresMinutes))->plainTextToken;

        return [
            'success' => true,
            'token' => $token,
            'user' => $user,
        ];
    }
}
