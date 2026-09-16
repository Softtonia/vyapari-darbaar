<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $defaultPassword = env('SEED_USER_PASSWORD', 'Soft@12345');

        $usersByRole = [
            'super_admin' => [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'username' => 'superadmin',
                'email' => env('SEED_SUPERADMIN_EMAIL', 'vijay.kumar@softtonia.com'),
                'phone_number' => '+919800000001',
                'status' => 'active',
                'is_default' => true,
                'must_change_password' => false,
            ],
            'admin' => [
                'first_name' => 'System',
                'last_name' => 'Admin',
                'username' => 'admin',
                'email' => env('SEED_ADMIN_USER_EMAIL', 'admin@vyaparidarbaar.com'),
                'phone_number' => '+919800000002',
                'status' => 'active',
                'is_default' => true,
                'must_change_password' => false,
            ],
            'editor' => [
                'first_name' => 'Content',
                'last_name' => 'Editor',
                'username' => 'editor',
                'email' => env('SEED_EDITOR_EMAIL', 'editor@vyaparidarbaar.com'),
                'phone_number' => '+919800000003',
                'status' => 'active',
                'is_default' => true,
                'must_change_password' => false,
            ],
            'trader' => [
                'first_name' => 'Market',
                'last_name' => 'Trader',
                'username' => 'trader',
                'email' => env('SEED_TRADER_EMAIL', 'trader@vyaparidarbaar.com'),
                'phone_number' => '+919800000004',
                'status' => 'active',
                'is_default' => true,
                'must_change_password' => false,
            ],
            'user' => [
                'first_name' => 'Regular',
                'last_name' => 'User',
                'username' => 'user',
                'email' => env('SEED_REGULAR_USER_EMAIL', 'user@vyaparidarbaar.com'),
                'phone_number' => '+919800000005',
                'status' => 'active',
                'is_default' => true,
                'must_change_password' => false,
            ],
            'subscriber' => [
                'first_name' => 'Premium',
                'last_name' => 'Subscriber',
                'username' => 'subscriber',
                'email' => env('SEED_SUBSCRIBER_EMAIL', 'subscriber@vyaparidarbaar.com'),
                'phone_number' => '+919800000006',
                'status' => 'active',
                'is_default' => true,
                'must_change_password' => false,
            ],
            'advertiser' => [
                'first_name' => 'Market',
                'last_name' => 'Advertiser',
                'username' => 'advertiser',
                'email' => env('SEED_ADVERTISER_EMAIL', 'advertiser@vyaparidarbaar.com'),
                'phone_number' => '+919800000007',
                'status' => 'active',
                'is_default' => true,
                'must_change_password' => false,
            ],
            'guest' => [
                'first_name' => 'Guest',
                'last_name' => 'User',
                'username' => 'guest',
                'email' => env('SEED_GUEST_EMAIL', 'guest@vyaparidarbaar.com'),
                'phone_number' => '+919800000008',
                'status' => 'active',
                'is_default' => true,
                'must_change_password' => false,
            ],
        ];

        // Seed predefined users for core roles
        foreach ($usersByRole as $roleName => $userData) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                ['slug' => $roleName, 'status' => true, 'is_default' => true]
            );

            $fullName = "{$userData['first_name']} {$userData['last_name']}";

            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'first_name' => $userData['first_name'],
                    'last_name' => $userData['last_name'],
                    'full_name' => $fullName,
                    'name' => $fullName,
                    'username' => $userData['username'],
                    'phone_number' => $userData['phone_number'],
                    'password' => Hash::make($defaultPassword),
                    'email_verified_at' => now(),
                    'status' => $userData['status'] ?? 'active',
                    'is_default' => $userData['is_default'] ?? true,
                    'must_change_password' => $userData['must_change_password'] ?? false,
                ]
            );

            if ($roleName === 'super_admin') {
                $adminRole = Role::firstOrCreate(
                    ['name' => 'admin', 'guard_name' => 'web'],
                    ['slug' => 'admin', 'status' => true, 'is_default' => true]
                );
                $user->syncRoles([$role, $adminRole]);
            } else {
                $user->syncRoles([$role]);
            }
        }

        // Dynamically ensure any additional roles in the database also have a corresponding seeded user
        $allRoles = Role::whereNotIn('name', array_keys($usersByRole))->get();
        $counter = 9;
        foreach ($allRoles as $customRole) {
            $cleanName = Str::title(str_replace(['_', '-'], ' ', $customRole->name));
            $username = Str::slug($customRole->name, '.');
            $email = "{$username}@vyaparidarbaar.com";
            $phone = '+9198000000' . str_pad((string) $counter++, 2, '0', STR_PAD_LEFT);

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => $cleanName,
                    'last_name' => 'User',
                    'full_name' => "{$cleanName} User",
                    'name' => "{$cleanName} User",
                    'username' => $username,
                    'phone_number' => $phone,
                    'password' => Hash::make($defaultPassword),
                    'email_verified_at' => now(),
                    'status' => 'active',
                    'is_default' => true,
                    'must_change_password' => false,
                ]
            );

            $user->syncRoles([$customRole]);
        }
    }
}
