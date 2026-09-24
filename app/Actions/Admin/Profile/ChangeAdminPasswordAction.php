<?php

namespace App\Actions\Admin\Profile;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Hash;

class ChangeAdminPasswordAction
{
    /**
     * Change the administrator's password.
     *
     * @throws HttpResponseException
     */
    public function execute(User $admin, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $admin->password)) {
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

        $admin->password = Hash::make($newPassword);
        // Clear must_change_password flag if set
        $admin->must_change_password = false;
        $admin->save();
    }
}
