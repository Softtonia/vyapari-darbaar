<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = [
            [
                'name' => 'admin',
                'guard_name' => 'admin',
                'slug' => 'admin',
                'status' => true,
                'is_system' => true,
            ],
            [
                'name' => 'editor',
                'guard_name' => 'admin',
                'slug' => 'editor',
                'status' => true,
                'is_system' => true,
            ],
            [
                'name' => 'user',
                'guard_name' => 'web',
                'slug' => 'user',
                'status' => true,
                'is_system' => true,
            ],
            [
                'name' => 'trader',
                'guard_name' => 'web',
                'slug' => 'trader',
                'status' => true,
                'is_system' => true,
            ],
            [
                'name' => 'subscriber',
                'guard_name' => 'web',
                'slug' => 'subscriber',
                'status' => true,
                'is_system' => true,
            ],
            [
                'name' => 'advertiser',
                'guard_name' => 'web',
                'slug' => 'advertiser',
                'status' => true,
                'is_system' => true,
            ],
            [
                'name' => 'guest',
                'guard_name' => 'web',
                'slug' => 'guest',
                'status' => true,
                'is_system' => true,
            ],
        ];

        foreach ($roles as $roleData) {
            $role = Role::where('slug', $roleData['slug'])
                ->orWhere(function ($query) use ($roleData) {
                    $query->where('name', $roleData['name'])
                        ->where('guard_name', $roleData['guard_name']);
                })->first();

            if ($role) {
                $role->update([
                    'name' => $roleData['name'],
                    'guard_name' => $roleData['guard_name'],
                    'slug' => $roleData['slug'],
                    'status' => $roleData['status'],
                    'is_system' => $roleData['is_system'],
                ]);
            } else {
                Role::create($roleData);
            }
        }
    }
}
