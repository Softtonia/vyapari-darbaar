<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultRoles = [
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'status' => true,
                'is_system' => true,
            ],
            [
                'name' => 'User',
                'slug' => 'user',
                'status' => true,
                'is_system' => true,
            ],
            [
                'name' => 'Guest',
                'slug' => 'guest',
                'status' => true,
                'is_system' => true,
            ],
        ];

        foreach ($defaultRoles as $role) {
            Role::updateOrCreate(
                ['slug' => $role['slug']],
                [
                    'name' => $role['name'],
                    'status' => $role['status'],
                    'is_system' => $role['is_system'],
                ]
            );
        }
    }
}
