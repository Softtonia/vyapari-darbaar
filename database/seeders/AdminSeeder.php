<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = env('SEED_ADMIN_EMAIL', 'admin@vyaparidarbaar.com');
        $password = env('SEED_ADMIN_PASSWORD', 'password');
        $name = env('SEED_ADMIN_NAME', 'System Administrator');

        Admin::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'status' => 'active',
            ]
        );
    }
}
