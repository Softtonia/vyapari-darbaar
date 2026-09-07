<?php

namespace App\Actions\Admin\Profile;

use App\Models\Admin;
use Illuminate\Support\Facades\DB;

class UpdateAdminProfileAction
{
    /**
     * Update admin profile name/first_name/last_name/email, clean stale password reset tokens, and revoke other sessions if email changes.
     *
     * @param  Admin  $admin
     * @param  array{first_name?: string, last_name?: string, name?: string, email?: string}  $data
     * @return Admin
     */
    public function execute(Admin $admin, array $data): Admin
    {
        return DB::transaction(function () use ($admin, $data) {
            if (isset($data['email'])) {
                $newEmail = strtolower(trim($data['email']));
                if ($newEmail !== strtolower(trim($admin->email))) {
                    $oldEmail = $admin->email;

                    // 1. Delete stale password reset tokens for the old email
                    DB::table('admin_password_reset_tokens')->where('email', $oldEmail)->delete();

                    // 2. Revoke all OTHER Admin Sanctum tokens while preserving current token
                    $currentTokenId = $admin->currentAccessToken()?->id;
                    if ($currentTokenId) {
                        $admin->tokens()->where('id', '!=', $currentTokenId)->delete();
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

            if (isset($data['name'])) {
                $admin->name = trim($data['name']);
            } elseif (isset($data['first_name']) || isset($data['last_name'])) {
                $admin->name = trim(($admin->first_name ?? '').' '.($admin->last_name ?? ''));
            }

            $admin->save();

            return $admin->fresh(['roles']);
        });
    }
}
