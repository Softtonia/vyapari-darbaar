<?php

namespace App\Actions\Admin\Profile;

use App\Models\Admin;
use Illuminate\Support\Facades\DB;

class UpdateAdminProfileAction
{
    /**
     * Update admin profile name/email, clean stale password reset tokens, and revoke other sessions if email changes.
     *
     * @param  Admin  $admin
     * @param  array{name?: string, email?: string}  $data
     * @return Admin
     */
    public function execute(Admin $admin, array $data): Admin
    {
        return DB::transaction(function () use ($admin, $data) {
            $emailChanged = false;

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
                    $emailChanged = true;
                }
            }

            if (isset($data['name'])) {
                $admin->name = trim($data['name']);
            }

            $admin->save();

            return $admin->fresh();
        });
    }
}
