<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Role;
use App\Models\User;
use App\Notifications\DatabaseCustomNotification;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_user_can_retrieve_notifications_and_unread_count(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('user');

        \App\Models\InAppNotification::create([
            'user_id' => $user->id,
            'title' => 'Welcome Notice',
            'body' => 'Welcome to the platform!',
            'type' => 'system',
            'is_read' => false,
        ]);

        \App\Models\InAppNotification::create([
            'user_id' => $user->id,
            'title' => 'Important Alert',
            'body' => 'Please verify your details.',
            'type' => 'alert',
            'is_read' => false,
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/user/notifications');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Notifications retrieved successfully.',
            ])
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.unread_count', 2)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'items' => [
                        '*' => [
                            'id',
                            'title',
                            'body',
                            'is_read',
                            'read_at',
                            'created_at',
                        ],
                    ],
                    'unread_count',
                    'pagination' => [
                        'current_page',
                        'per_page',
                        'total',
                        'last_page',
                    ],
                ],
            ]);

        $countResponse = $this->getJson('/api/user/notifications/unread-count');
        $countResponse->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'unread_count' => 2,
                ],
            ]);
    }

    public function test_user_can_filter_notifications_by_status(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('user');

        \App\Models\InAppNotification::create([
            'user_id' => $user->id,
            'title' => 'Unread Message',
            'body' => 'This is unread',
            'is_read' => false,
        ]);

        \App\Models\InAppNotification::create([
            'user_id' => $user->id,
            'title' => 'Read Message',
            'body' => 'This will be marked read',
            'is_read' => true,
            'read_at' => now(),
        ]);

        Sanctum::actingAs($user, ['*']);

        $unreadResponse = $this->getJson('/api/user/notifications?status=unread');
        $unreadResponse->assertStatus(200)
            ->assertJsonCount(1, 'data.items');

        $readResponse = $this->getJson('/api/user/notifications?status=read');
        $readResponse->assertStatus(200)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_user_can_mark_single_notification_as_read(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('user');

        $notification = \App\Models\InAppNotification::create([
            'user_id' => $user->id,
            'title' => 'Test Notification',
            'body' => 'Read me',
            'read_at' => null,
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->patchJson("/api/user/notifications/{$notification->id}/read");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Notification marked as read successfully.',
                'data' => [
                    'id' => $notification->id,
                    'is_read' => true,
                ],
            ]);

        $this->assertEquals(0, $user->unreadInAppNotifications()->count());
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('user');

        \App\Models\InAppNotification::create([
            'user_id' => $user->id,
            'title' => 'One',
            'body' => 'First',
            'read_at' => null,
        ]);
        \App\Models\InAppNotification::create([
            'user_id' => $user->id,
            'title' => 'Two',
            'body' => 'Second',
            'read_at' => null,
        ]);

        $this->assertEquals(2, $user->unreadInAppNotifications()->count());

        Sanctum::actingAs($user, ['*']);

        $response = $this->patchJson('/api/user/notifications/read-all');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'All notifications marked as read successfully.',
                'data' => [
                    'unread_count' => 0,
                ],
            ]);

        $this->assertEquals(0, $user->unreadInAppNotifications()->count());
    }

    public function test_user_can_delete_single_notification(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('user');

        $notification = \App\Models\InAppNotification::create([
            'user_id' => $user->id,
            'title' => 'To Delete',
            'body' => 'Delete this',
            'read_at' => null,
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->deleteJson("/api/user/notifications/{$notification->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Notification deleted successfully.',
            ]);

        $this->assertDatabaseMissing('in_app_notifications', ['id' => $notification->id]);
    }

    public function test_user_can_clear_read_notifications(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('user');

        \App\Models\InAppNotification::create([
            'user_id' => $user->id,
            'title' => 'Keep Unread',
            'body' => 'Unread',
            'read_at' => null,
        ]);
        \App\Models\InAppNotification::create([
            'user_id' => $user->id,
            'title' => 'To Clear',
            'body' => 'Read',
            'read_at' => now(),
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->deleteJson('/api/user/notifications/read');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Read notifications cleared successfully.',
                'data' => [
                    'deleted_count' => 1,
                ],
            ]);

        $this->assertEquals(1, $user->inAppNotifications()->count());
    }

    public function test_user_cannot_access_or_modify_another_users_notification(): void
    {
        $user1 = User::factory()->create(['status' => 'active']);
        $user1->assignRole('user');

        $user2 = User::factory()->create(['status' => 'active']);
        $user2->assignRole('user');

        $user2Notification = \App\Models\InAppNotification::create([
            'user_id' => $user2->id,
            'title' => 'Secret',
            'body' => 'For user 2 only',
            'is_read' => false,
        ]);

        Sanctum::actingAs($user1, ['*']);

        $readResponse = $this->patchJson("/api/user/notifications/{$user2Notification->id}/read");
        $readResponse->assertStatus(404);

        $deleteResponse = $this->deleteJson("/api/user/notifications/{$user2Notification->id}");
        $deleteResponse->assertStatus(404);
    }

    public function test_admin_can_send_notification_to_single_user(): void
    {
        $admin = Admin::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin_notif1@example.com',
            'password' => Hash::make('AdminPass123!'),
            'status' => 'active',
        ]);
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'admin')->first();
        $admin->assignRole($adminRole);

        $token = $admin->createToken('admin-token', ['*'])->plainTextToken;

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('user');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/notifications/send', [
                'notification_type' => 'in_app',
                'audience_type' => 'single_user',
                'user_id' => $user->id,
                'title' => 'Admin Direct Message',
                'body' => 'Hello User from Admin',
                'send_now' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'target_count' => 1,
                ],
            ]);

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $user->id,
            'title' => 'Admin Direct Message',
        ]);
    }

    public function test_admin_can_broadcast_notification_to_selected_users(): void
    {
        $admin = Admin::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin_notif2@example.com',
            'password' => Hash::make('AdminPass123!'),
            'status' => 'active',
        ]);
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'admin')->first();
        $admin->assignRole($adminRole);

        $token = $admin->createToken('admin-token', ['*'])->plainTextToken;

        $trader1 = User::factory()->create(['status' => 'active']);
        $trader2 = User::factory()->create(['status' => 'active']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/notifications/send', [
                'notification_type' => 'in_app',
                'audience_type' => 'selected_users',
                'user_ids' => [$trader1->id, $trader2->id],
                'title' => 'Mandi Price Update',
                'body' => 'New prices published for traders.',
                'send_now' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'target_count' => 2,
                ],
            ]);

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $trader1->id,
            'title' => 'Mandi Price Update',
        ]);
        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $trader2->id,
            'title' => 'Mandi Price Update',
        ]);
    }

    public function test_admin_can_broadcast_notification_to_all_active_users(): void
    {
        $admin = Admin::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin_notif3@example.com',
            'password' => Hash::make('AdminPass123!'),
            'status' => 'active',
        ]);
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'admin')->first();
        $admin->assignRole($adminRole);

        $token = $admin->createToken('admin-token', ['*'])->plainTextToken;

        $activeUser1 = User::factory()->create(['status' => 'active']);
        $activeUser2 = User::factory()->create(['status' => 'active']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/notifications/send', [
                'notification_type' => 'in_app',
                'audience_type' => 'all_users',
                'title' => 'System Maintenance Alert',
                'body' => 'Platform maintenance at midnight.',
                'send_now' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'target_count' => 2,
                ],
            ]);

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $activeUser1->id,
            'title' => 'System Maintenance Alert',
        ]);
        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $activeUser2->id,
            'title' => 'System Maintenance Alert',
        ]);
    }
}
