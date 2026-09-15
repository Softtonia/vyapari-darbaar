<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\InAppNotification;
use App\Models\User;
use App\Models\UserActivity;
use App\Services\Firebase\NotificationDeviceService;
use App\Services\UserActivityService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserActivityTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = Admin::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'name' => 'Super Admin',
            'email' => 'admin@vyaparidarbar.com',
            'password' => Hash::make('AdminPass@123'),
            'status' => 'active',
        ]);

        $this->adminToken = $this->admin->createToken('admin-token')->plainTextToken;
    }

    public function test_admin_can_view_global_user_activities_feed(): void
    {
        $userA = User::factory()->create(['status' => 'active', 'first_name' => 'Alice']);
        $userB = User::factory()->create(['status' => 'active', 'first_name' => 'Bob']);

        UserActivityService::log($userA, 'login', 'Alice logged in');
        UserActivityService::log($userB, 'profile_update', 'Bob updated profile');

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/admin/user-activities');

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
                            'user_id',
                            'user' => [
                                'id',
                                'full_name',
                                'username',
                                'email',
                                'status',
                                'role',
                            ],
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
    }

    public function test_admin_can_filter_global_activities_by_event_user_and_search(): void
    {
        $userA = User::factory()->create([
            'status' => 'active',
            'first_name' => 'Charlie',
            'username' => 'charlie.trader',
        ]);
        $userB = User::factory()->create([
            'status' => 'active',
            'first_name' => 'David',
            'username' => 'david.trader',
        ]);

        UserActivityService::log($userA, 'login', 'Charlie logged in');
        UserActivityService::log($userA, 'password_change', 'Charlie changed password');
        UserActivityService::log($userB, 'login', 'David logged in');

        // Filter by user_id
        $resUser = $this->withToken($this->adminToken)
            ->getJson("/api/admin/user-activities?user_id={$userA->id}");
        $resUser->assertStatus(200)->assertJsonCount(2, 'data.items');

        // Filter by event
        $resEvent = $this->withToken($this->adminToken)
            ->getJson('/api/admin/user-activities?event=password_change');
        $resEvent->assertStatus(200)->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.event', 'password_change');

        // Search by username
        $resSearch = $this->withToken($this->adminToken)
            ->getJson('/api/admin/user-activities?search=charlie');
        $resSearch->assertStatus(200)->assertJsonCount(2, 'data.items');
    }

    public function test_admin_can_view_specific_user_activities(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'first_name' => 'Target',
            'last_name' => 'User',
            'username' => 'target.user',
        ]);
        $otherUser = User::factory()->create(['status' => 'active']);

        UserActivityService::log($user, 'login', 'Target logged in');
        UserActivityService::log($user, 'device_registered', 'Target device registered');
        UserActivityService::log($otherUser, 'login', 'Other logged in');

        $response = $this->withToken($this->adminToken)
            ->getJson("/api/admin/users/{$user->id}/activities");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'username' => 'target.user',
                    ],
                ],
            ])
            ->assertJsonCount(2, 'data.items');
    }

    public function test_user_token_and_guest_cannot_access_admin_activity_endpoints(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $userToken = $user->createToken('user-token')->plainTextToken;

        // Guest
        $this->getJson('/api/admin/user-activities')->assertStatus(401);
        $this->getJson("/api/admin/users/{$user->id}/activities")->assertStatus(401);

        // User token
        $this->withToken($userToken)->getJson('/api/admin/user-activities')->assertStatus(403);
        $this->withToken($userToken)->getJson("/api/admin/users/{$user->id}/activities")->assertStatus(403);
    }

    public function test_failed_login_with_wrong_password_logs_activity(): void
    {
        $user = User::factory()->create([
            'username' => 'wrongpass.user',
            'password' => Hash::make('CorrectPassword#123'),
            'status' => 'active',
        ]);

        $this->postJson('/api/user/login', [
            'username' => 'wrongpass.user',
            'password' => 'WrongPassword#999',
        ])->assertStatus(401);

        $this->assertDatabaseHas('user_activities', [
            'user_id' => $user->id,
            'event' => 'login_failed',
        ]);
    }

    public function test_token_refresh_logs_activity(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/user/refresh-token')
            ->assertStatus(200);

        $this->assertDatabaseHas('user_activities', [
            'user_id' => $user->id,
            'event' => 'token_refreshed',
        ]);
    }

    public function test_device_registration_and_deactivation_logs_activity(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user, ['*']);

        $token = 'fcm_test_token_12345678901234567890';

        // Register device
        $this->postJson('/api/notifications/devices', [
            'fcm_token' => $token,
            'device_type' => 'android',
            'device_model' => 'Pixel 8',
        ])->assertStatus(200);

        $this->assertDatabaseHas('user_activities', [
            'user_id' => $user->id,
            'event' => 'device_registered',
        ]);

        // Deactivate device
        $this->deleteJson('/api/notifications/devices', [
            'fcm_token' => $token,
        ])->assertStatus(200);

        $this->assertDatabaseHas('user_activities', [
            'user_id' => $user->id,
            'event' => 'device_unregistered',
        ]);
    }

    public function test_notification_actions_log_activity(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $notification = InAppNotification::create([
            'user_id' => $user->id,
            'title' => 'Test Notice',
            'body' => 'Notice body content',
            'read_at' => null,
        ]);

        Sanctum::actingAs($user, ['*']);

        // Mark as read
        $this->patchJson("/api/user/notifications/{$notification->id}/read")
            ->assertStatus(200);

        $this->assertDatabaseHas('user_activities', [
            'user_id' => $user->id,
            'event' => 'notification_read',
        ]);

        // Mark all as read
        $this->patchJson('/api/user/notifications/read-all')
            ->assertStatus(200);

        $this->assertDatabaseHas('user_activities', [
            'user_id' => $user->id,
            'event' => 'notifications_mark_all_read',
        ]);

        // Clear read
        $this->deleteJson('/api/user/notifications/read')
            ->assertStatus(200);

        $this->assertDatabaseHas('user_activities', [
            'user_id' => $user->id,
            'event' => 'notifications_cleared',
        ]);
    }

    public function test_admin_can_view_own_activities(): void
    {
        UserActivityService::log($this->admin, 'login', 'Super Admin logged in');
        UserActivityService::log($this->admin, 'profile_updated', 'Super Admin updated profile');

        $user = User::factory()->create(['status' => 'active']);
        UserActivityService::log($user, 'login', 'User logged in');

        $response1 = $this->withToken($this->adminToken)->getJson('/api/admin/my-activities');
        $response1->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Your activities retrieved successfully.',
            ])
            ->assertJsonCount(2, 'data.items');

        $response2 = $this->withToken($this->adminToken)->getJson('/api/admin/activities/own');
        $response2->assertStatus(200)
            ->assertJsonCount(2, 'data.items');
    }

    public function test_admin_can_view_global_login_history_with_status_filter(): void
    {
        $userA = User::factory()->create(['status' => 'active', 'first_name' => 'Alice']);
        $userB = User::factory()->create(['status' => 'active', 'first_name' => 'Bob']);

        UserActivityService::log($userA, 'login', 'Alice logged in');
        UserActivityService::log($userA, 'login_failed', 'Alice failed login');
        UserActivityService::log($userB, 'logout', 'Bob logged out');
        UserActivityService::log($userB, 'profile_update', 'Bob updated profile'); // Not a login event

        $response = $this->withToken($this->adminToken)->getJson('/api/admin/user-logins');
        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Login history retrieved successfully.',
            ])
            ->assertJsonCount(3, 'data.items'); // Only login, login_failed, logout

        // Filter by status=failed
        $resFailed = $this->withToken($this->adminToken)->getJson('/api/admin/user-logins?status=failed');
        $resFailed->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.event', 'login_failed');

        // Filter by status=success
        $resSuccess = $this->withToken($this->adminToken)->getJson('/api/admin/user-logins?status=success');
        $resSuccess->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.event', 'login');
    }

    public function test_admin_can_view_own_logins_history(): void
    {
        UserActivityService::log($this->admin, 'login', 'Admin logged in');
        UserActivityService::log($this->admin, 'logout', 'Admin logged out');

        $user = User::factory()->create(['status' => 'active']);
        UserActivityService::log($user, 'login', 'User logged in');

        $response = $this->withToken($this->adminToken)->getJson('/api/admin/my-logins');
        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Your login history retrieved successfully.',
            ])
            ->assertJsonCount(2, 'data.items');
    }

    public function test_admin_can_view_specific_user_login_history(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        UserActivityService::log($user, 'login', 'User logged in');
        UserActivityService::log($user, 'profile_updated', 'User updated profile');

        $response = $this->withToken($this->adminToken)->getJson("/api/admin/users/{$user->id}/logins");
        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'User login history retrieved successfully.',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                    ],
                ],
            ])
            ->assertJsonCount(1, 'data.items');
    }

    public function test_user_can_view_own_login_history(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $userToken = $user->createToken('user-token')->plainTextToken;

        UserActivityService::log($user, 'login', 'User logged in');
        UserActivityService::log($user, 'logout', 'User logged out');

        $response = $this->withToken($userToken)->getJson('/api/user/logins');
        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Login history retrieved successfully.',
            ])
            ->assertJsonCount(2, 'data.items');
    }
}
