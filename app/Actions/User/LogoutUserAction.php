<?php

namespace App\Actions\User;

use App\Models\User;
use App\Services\Firebase\NotificationDeviceService;
use App\Services\UserActivityService;

class LogoutUserAction
{
    public function __construct(
        protected NotificationDeviceService $deviceService
    ) {}

    /**
     * Revoke the current access token and optionally deactivate current device token.
     *
     * @param  User  $user
     * @param  string|null  $fcmToken
     * @return void
     */
    public function execute(User $user, ?string $fcmToken = null): void
    {
        UserActivityService::log(
            $user,
            'logout',
            'User logged out'
        );

        if (! empty($fcmToken)) {
            $this->deviceService->deactivateUserDevice($user, $fcmToken);
        }

        $user->currentAccessToken()?->delete();
    }
}

