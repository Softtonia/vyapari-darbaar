<?php

namespace App\Actions\User\Profile;

use App\Enums\NotificationType;
use App\Jobs\SendUserNotificationJob;
use App\Models\User;
use App\Services\UserActivityService;
use Illuminate\Support\Facades\DB;

class UpdateUserProfileAction
{
    /**
     * Update user profile first_name/last_name/phone_number/email,
     * and revoke other sessions if email changes.
     *
     * @param array{
     *     first_name?: string,
     *     last_name?: string,
     *     phone_number?: string|null,
     *     email?: string
     * } $data
     */
    public function execute(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            if (isset($data['email'])) {
                $newEmail = strtolower(trim($data['email']));

                if ($newEmail !== strtolower(trim($user->email))) {
                    $currentTokenId = $user->currentAccessToken()?->id;

                    if ($currentTokenId) {
                        $user->tokens()
                            ->where('id', '!=', $currentTokenId)
                            ->delete();
                    }

                    $user->email = $newEmail;
                }
            }

            if (isset($data['first_name'])) {
                $user->first_name = trim($data['first_name']);
            }

            if (isset($data['last_name'])) {
                $user->last_name = trim($data['last_name']);
            }

            if (array_key_exists('phone_number', $data)) {
                $user->phone_number = $data['phone_number'];
            }

            $user->save();

            UserActivityService::log(
                $user,
                'profile_update',
                'User updated profile information',
                array_keys($data)
            );

            SendUserNotificationJob::dispatch(
                $user->id,
                'Profile Updated',
                'Hello {{user_first_name}}, your profile details have been updated successfully.',
                NotificationType::IN_APP
            );

            return $user->fresh(['roles']);
        });
    }
}