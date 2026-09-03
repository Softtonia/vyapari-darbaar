<?php

namespace App\Actions\Admin\User;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class BulkDeleteUsersAction
{
    /**
     * Revoke tokens and delete multiple users in a single transaction.
     *
     * @param  list<int>  $ids
     * @return int Number of deleted users
     */
    public function execute(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            // Delete associated personal access tokens
            PersonalAccessToken::query()
                ->where('tokenable_type', User::class)
                ->whereIn('tokenable_id', $ids)
                ->delete();

            // Delete the users
            return User::query()->whereIn('id', $ids)->delete();
        });
    }
}
