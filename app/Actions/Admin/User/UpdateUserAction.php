<?php

namespace App\Actions\Admin\User;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateUserAction
{
    /**
     * Update the user's information and sync Spatie role if provided.
     *
     * @param  User  $user
     * @param  array{first_name: string, last_name: string, name: string, email: string, phone_number?: string|null, role?: string|null}  $data
     * @return User
     */
    public function execute(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $updateData = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'name' => $data['name'],
                'email' => $data['email'],
            ];

            if (array_key_exists('phone_number', $data)) {
                $updateData['phone_number'] = $data['phone_number'];
            }

            $user->update($updateData);

            if (! empty($data['role'])) {
                $user->syncRoles([$data['role']]);
            }

            return $user->fresh(['roles']);
        });
    }
}
