<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Role;
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
        $firstName = env('SEED_ADMIN_FIRST_NAME', 'System');
        $lastName = env('SEED_ADMIN_LAST_NAME', 'Administrator');
        $name = env('SEED_ADMIN_NAME', "{$firstName} {$lastName}");

        $admin = Admin::updateOrCreate(
            ['email' => $email],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'name' => $name,
                'password' => Hash::make($password),
                'status' => 'active',
            ]
        );

        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'admin'],
            ['slug' => 'admin', 'status' => true, 'is_system' => true]
        );

        $admin->assignRole($adminRole);
    }
}
