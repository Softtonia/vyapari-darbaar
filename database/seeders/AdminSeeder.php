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

        $email = env('SEED_ADMIN_EMAIL', 'vijay.kumar@softtonia.com');
        $password = env('SEED_ADMIN_PASSWORD', 'Soft@12345');
        $firstName = env('SEED_ADMIN_FIRST_NAME', 'Super');
        $lastName = env('SEED_ADMIN_LAST_NAME', 'Admin');
        $fullName = env('SEED_ADMIN_NAME', "{$firstName} {$lastName}");
        $username = env('SEED_ADMIN_USERNAME', 'super.admin');

        $superAdmin = User::updateOrCreate(
            ['email' => $email],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'full_name' => $fullName,
                'name' => $fullName,
                'username' => $username,
                'password' => Hash::make($password),
                'status' => 'active',
                'is_default' => true,
                'must_change_password' => false,
            ]
        );

        $superAdminRole = Role::firstOrCreate(
            ['name' => 'super_admin', 'guard_name' => 'web'],
            ['slug' => 'super_admin', 'status' => true, 'is_default' => true]
        );

        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['slug' => 'admin', 'status' => true, 'is_default' => true]
        );

        $superAdmin->assignRole([$superAdminRole, $adminRole]);
    }
}
