<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create an admin user with specified permissions or roles.
     *
     * @param  list<string>  $permissions
     * @param  list<string>  $roles
     */
    protected function createAdminUser(array $permissions = [], array $roles = []): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        /** @var User $user */
        $user = User::factory()->create([
            'status' => 'active',
            'is_default' => true,
        ]);

        foreach ($roles as $roleName) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                ['slug' => $roleName, 'status' => true, 'is_default' => true]
            );
            $user->assignRole($role);
        }

        if (! empty($permissions)) {
            foreach ($permissions as $perm) {
                \Spatie\Permission\Models\Permission::findOrCreate($perm, 'web');
            }
            $user->givePermissionTo($permissions);
        }

        return $user;
    }

    /**
     * Create an admin user and return bearer token.
     *
     * @param  list<string>  $permissions
     * @param  list<string>  $roles
     */
    protected function createAdminToken(array $permissions = [], array $roles = []): string
    {
        $admin = $this->createAdminUser($permissions, $roles);

        return $admin->createToken('admin-token')->plainTextToken;
    }
}
