<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $superAdminRole = Role::firstOrCreate(
            ['name' => 'super_admin', 'guard_name' => 'web'],
            ['slug' => 'super_admin', 'status' => true, 'is_default' => true]
        );

        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['slug' => 'admin', 'status' => true, 'is_default' => true]
        );

        $admins = [
            [
                'email' => 'sales@softtonia.com',
                'password' => 'Soft@12345',
                'first_name' => 'Sales',
                'last_name' => 'Admin',
                'full_name' => 'Sales Admin',
                'name' => 'Sales Admin',
                'username' => 'sales.softtonia',
                'role' => $adminRole,
            ],
            [
                'email' => env('SEED_ADMIN_EMAIL', 'vijay.kumar@softtonia.com'),
                'password' => env('SEED_ADMIN_PASSWORD', 'Soft@12345'),
                'first_name' => env('SEED_ADMIN_FIRST_NAME', 'Super'),
                'last_name' => env('SEED_ADMIN_LAST_NAME', 'Admin'),
                'full_name' => env('SEED_ADMIN_NAME', 'Super Admin'),
                'name' => env('SEED_ADMIN_NAME', 'Super Admin'),
                'username' => env('SEED_ADMIN_USERNAME', 'super.admin'),
                'role' => $superAdminRole,
            ],
        ];

        foreach ($admins as $adminData) {
            $adminUser = User::updateOrCreate(
                ['email' => $adminData['email']],
                [
                    'first_name' => $adminData['first_name'],
                    'last_name' => $adminData['last_name'],
                    'full_name' => $adminData['full_name'],
                    'name' => $adminData['name'],
                    'username' => $adminData['username'],
                    'password' => Hash::make($adminData['password']),
                    'status' => 'active',
                    'is_default' => true,
                    'must_change_password' => false,
                    'email_verified_at' => now(),
                ]
            );

            $adminUser->syncRoles([$adminData['role']]);
        }
    }
}
