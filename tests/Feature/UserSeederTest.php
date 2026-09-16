<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_seeder_creates_users_for_all_standard_roles(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(UserSeeder::class);

        $expectedRoles = [
            'super_admin',
            'admin',
            'editor',
            'trader',
            'user',
            'subscriber',
            'advertiser',
            'guest',
        ];

        foreach ($expectedRoles as $roleName) {
            $user = User::whereHas('roles', function ($query) use ($roleName) {
                $query->where('name', $roleName);
            })->first();

            $this->assertNotNull($user, "User for role {$roleName} should exist.");
            $this->assertTrue($user->hasRole($roleName));
            $this->assertEquals('active', $user->status);
            $this->assertTrue(Hash::check('Soft@12345', $user->password));
            $this->assertNotNull($user->email_verified_at);
        }
    }

    public function test_user_seeder_handles_custom_dynamic_roles(): void
    {
        $this->seed(RoleSeeder::class);

        Role::create([
            'name' => 'auditor',
            'guard_name' => 'web',
            'slug' => 'auditor',
            'status' => true,
            'is_default' => false,
        ]);

        $this->seed(UserSeeder::class);

        $auditorUser = User::whereHas('roles', function ($query) {
            $query->where('name', 'auditor');
        })->first();

        $this->assertNotNull($auditorUser);
        $this->assertTrue($auditorUser->hasRole('auditor'));
        $this->assertEquals('auditor@vyaparidarbaar.com', $auditorUser->email);
    }

    public function test_user_seeder_is_idempotent(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(UserSeeder::class);
        $countAfterFirstSeed = User::count();

        // Run second time
        $this->seed(UserSeeder::class);
        $countAfterSecondSeed = User::count();

        $this->assertEquals($countAfterFirstSeed, $countAfterSecondSeed);
    }
}
