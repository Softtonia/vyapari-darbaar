<?php

namespace App\Actions\Admin\Profile;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateAdminProfileAction
{
    /**
     * Update admin profile first_name/last_name/email, clean stale password reset tokens,
     * and revoke other sessions if email changes.
     *
     * @param array{first_name?: string, last_name?: string, email?: string} $data
     */
    public function execute(User $admin, array $data): User
    {
        return DB::transaction(function () use ($admin, $data) {
            if (isset($data['email'])) {
                $newEmail = strtolower(trim($data['email']));

                if ($newEmail !== strtolower(trim($admin->email))) {
                    $oldEmail = $admin->email;

                    // Delete stale password reset tokens for the old email
                    DB::table('password_reset_tokens')
                        ->where('email', $oldEmail)
                        ->delete();

                    // Revoke all other Admin Sanctum tokens while preserving current token
                    $currentTokenId = $admin->currentAccessToken()?->id;

                    if ($currentTokenId) {
                        $admin->tokens()
                            ->where('id', '!=', $currentTokenId)
                            ->delete();
                    }

                    $admin->email = $newEmail;
                }
            }

            if (isset($data['first_name'])) {
                $admin->first_name = trim($data['first_name']);
            }

            if (isset($data['last_name'])) {
                $admin->last_name = trim($data['last_name']);
            }

            $admin->save();

            return $admin->fresh(['roles']);
        });
    }
}