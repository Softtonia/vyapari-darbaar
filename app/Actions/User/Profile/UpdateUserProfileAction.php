<?php

namespace App\Actions\User\Profile;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateUserProfileAction
{
    /**
     * Update user profile (name, phone_number, email), revoke other sessions if email changes.
     *
     * @param  User  $user
     * @param  array{first_name?: string, last_name?: string, name?: string, phone_number?: string|null, email?: string}  $data
     * @return User
     */
    public function execute(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            if (isset($data['email'])) {
                $newEmail = strtolower(trim($data['email']));
                if ($newEmail !== strtolower(trim($user->email))) {
                    // Revoke all OTHER User Sanctum tokens while preserving current token
                    $currentTokenId = $user->currentAccessToken()?->id;
                    if ($currentTokenId) {
                        $user->tokens()->where('id', '!=', $currentTokenId)->delete();
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

            if (isset($data['name'])) {
                $user->name = trim($data['name']);
            } elseif (isset($data['first_name']) || isset($data['last_name'])) {
                $user->name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
            }

            if (array_key_exists('phone_number', $data)) {
                $user->phone_number = $data['phone_number'];
            }

            $user->save();

            \App\Services\UserActivityService::log(
                $user,
                'profile_update',
                'User updated profile information',
                array_keys($data)
            );

            return $user->fresh(['roles']);
        });
    }
}
