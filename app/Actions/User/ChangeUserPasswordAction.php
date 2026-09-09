<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Hash;

class ChangeUserPasswordAction
{
    /**
     * Change user password, clear must_change_password flag, and revoke all other device tokens.
     *
     * @param  User  $user
     * @param  string  $currentPassword
     * @param  string  $newPassword
     * @return void
     *
     * @throws HttpResponseException
     */
    public function execute(User $user, string $currentPassword, string $newPassword): void
    {
        // Verify current password
        if (! Hash::check($currentPassword, $user->password)) {
            throw new HttpResponseException(
                response()->json([
                    'status' => false,
                    'message' => 'The provided current password does not match our records.',
                    'errors' => [
                        'current_password' => ['The provided current password does not match our records.'],
                    ],
                ], 422)
            );
        }

        // Prevent reusing same password
        if (Hash::check($newPassword, $user->password)) {
            throw new HttpResponseException(
                response()->json([
                    'status' => false,
                    'message' => 'The new password cannot be the same as the current password.',
                    'errors' => [
                        'password' => ['The new password cannot be the same as the current password.'],
                    ],
                ], 422)
            );
        }

        // Update password and clear must_change_password
        $user->update([
            'password' => Hash::make($newPassword),
            'must_change_password' => false,
        ]);

        \App\Services\UserActivityService::log(
            $user,
            'password_change',
            'User changed account password'
        );

        // Revoke all OTHER device tokens while preserving current token
        $currentTokenId = $user->currentAccessToken()?->id;
        if ($currentTokenId) {
            $user->tokens()->where('id', '!=', $currentTokenId)->delete();
        }
    }
}
