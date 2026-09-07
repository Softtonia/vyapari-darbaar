<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\DB;
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

        if (! $user) {
            return [
                'success' => false,
                'message' => 'No account found with this username.',
                'code' => 401,
            ];
        }

        if (! Hash::check($credentials['password'], $user->password)) {
            return [
                'success' => false,
                'message' => 'Incorrect password.',
                'code' => 401,
            ];
        }

        if ($user->status !== 'active') {
            return [
                'success' => false,
                'message' => 'Account is inactive. Please contact administrator.',
                'code' => 403,
            ];
        }

        // Check if an unexpired active token exists for this user and device
        $activeToken = DB::table('personal_access_tokens')
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->where('name', $deviceName)
            ->where('expires_at', '>', now())
            ->whereNotNull('plain_token')
            ->latest('id')
            ->first();

        if ($activeToken && ! empty($activeToken->plain_token)) {
            return [
                'success' => true,
                'token' => $activeToken->plain_token,
                'user' => $user,
            ];
        }

        $expiresMinutes = (int) (config('sanctum.expiration') ?? 1440);
        $tokenResult = $user->createToken($deviceName, ['*'], now()->addMinutes($expiresMinutes));

        DB::table('personal_access_tokens')
            ->where('id', $tokenResult->accessToken->id)
            ->update([
                'plain_token' => $tokenResult->plainTextToken,
            ]);

        return [
            'success' => true,
            'token' => $tokenResult->plainTextToken,
            'user' => $user,
        ];
    }
}
