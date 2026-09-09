<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserActivity;
use App\Services\OtpService;
use App\Services\UserActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_user_can_view_own_paginated_activities(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);
        $user->assignRole('user');

        UserActivityService::log($user, 'login', 'User logged in');
        UserActivityService::log($user, 'profile_update', 'User updated profile');

        $otherUser = User::factory()->create(['status' => 'active']);
        UserActivityService::log($otherUser, 'login', 'Other user logged in');

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/user/activities');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'User activities retrieved successfully.',
            ])
            ->assertJsonCount(2, 'data.items')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'items' => [
                        '*' => [
                            'id',
                            'event',
                            'description',
                            'ip_address',
                            'user_agent',
                            'properties',
                            'created_at',
                        ],
                    ],
                    'pagination' => [
                        'current_page',
                        'per_page',
                        'total',
                        'last_page',
                    ],
                ],
            ]);

        $this->assertEquals(2, $response->json('data.pagination.total'));
    }

    public function test_user_can_filter_activities_by_event(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('user');

        UserActivityService::log($user, 'login', 'User logged in');
        UserActivityService::log($user, 'profile_update', 'User updated profile');

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/user/activities?event=login');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.items');

        $this->assertEquals('login', $response->json('data.items.0.event'));
    }

    public function test_unauthenticated_user_cannot_view_activities(): void
    {
        $response = $this->getJson('/api/user/activities');
        $response->assertStatus(401);
    }

    public function test_activity_is_logged_on_login(): void
    {
        $user = User::factory()->create([
            'username' => 'login_user_test',
            'email' => 'login_test@example.com',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $user->assignRole('user');

        $response = $this->postJson('/api/user/login', [
            'username' => 'login_user_test',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('user_activities', [
            'user_id' => $user->id,
            'event' => 'login',
        ]);
    }

    public function test_activity_is_logged_on_logout(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('user');

        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/user/logout');
        $response->assertStatus(200);

        $this->assertDatabaseHas('user_activities', [
            'user_id' => $user->id,
            'event' => 'logout',
        ]);
    }

    public function test_activity_is_logged_on_change_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword123!'),
            'status' => 'active',
        ]);
        $user->assignRole('user');

        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/user/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewSecret456@!',
            'password_confirmation' => 'NewSecret456@!',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('user_activities', [
            'user_id' => $user->id,
            'event' => 'password_change',
        ]);
    }

    public function test_activity_is_logged_on_profile_update(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('user');

        Sanctum::actingAs($user, ['*']);

        $response = $this->patchJson('/api/user/profile', [
            'first_name' => 'UpdatedFirst',
            'last_name' => 'UpdatedLast',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('user_activities', [
            'user_id' => $user->id,
            'event' => 'profile_update',
        ]);
    }

    public function test_activity_is_logged_on_password_reset(): void
    {
        $user = User::factory()->create([
            'email' => 'reset_act@example.com',
            'status' => 'active',
        ]);
        $user->assignRole('user');

        $token = Password::broker('users')->createToken($user);

        $response = $this->postJson('/api/user/reset-password', [
            'email' => 'reset_act@example.com',
            'token' => $token,
            'password' => 'BrandNewPass123!@#',
            'password_confirmation' => 'BrandNewPass123!@#',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('user_activities', [
            'user_id' => $user->id,
            'event' => 'password_reset',
        ]);
    }
}
