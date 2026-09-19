<?php

namespace App\Actions\User;

use App\Models\User;
use App\Services\UserActivityService;
use Illuminate\Support\Facades\DB;

class RefreshTokenAction
{
    /**
     * Revoke the current access token and issue a fresh Sanctum token.
     *
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

        UserActivityService::log(
            $user,
            'token_refreshed',
            "User refreshed access token from device '{$deviceName}'",
            ['device_name' => $deviceName]
        );

        return [
            'token' => $tokenResult->plainTextToken,
            'token_type' => 'Bearer',
        ];
    }
}
