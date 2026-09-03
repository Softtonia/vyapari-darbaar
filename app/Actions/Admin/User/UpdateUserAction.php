<?php

namespace App\Actions\Admin\User;

use App\Models\User;

class UpdateUserAction
{
    /**
     * Update the user's name and email only.
     *
     * @param  User  $user
     * @param  array{name: string, email: string}  $data
     * @return User
     */
    public function execute(User $user, array $data): User
    {
        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        return $user->fresh();
    }
}
