<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class RefreshTokenAction
{
    /**
     * Revoke the current access token and issue a fresh Sanctum token.
     *
     * @param  User  $user
     * @param  string  $deviceName
     * @return array{token: string, token_type: string}
     */
    public function execute(User $user, string $deviceName = 'user-device'): array
    {
        $currentToken = $user->currentAccessToken();

        if ($currentToken) {
            $currentToken->delete();
        }

        $expiresMinutes = (int) (config('sanctum.expiration') ?? 1440);
        $tokenResult = $user->createToken($deviceName, ['*'], now()->addMinutes($expiresMinutes));

        DB::table('personal_access_tokens')
            ->where('id', $tokenResult->accessToken->id)
            ->update([
                'plain_token' => $tokenResult->plainTextToken,
            ]);

        return [
            'token' => $tokenResult->plainTextToken,
            'token_type' => 'Bearer',
        ];
    }
}
