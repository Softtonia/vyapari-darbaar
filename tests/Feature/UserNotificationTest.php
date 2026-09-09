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

        $user->notify(new DatabaseCustomNotification([
            'title' => 'Welcome Notice',
            'message' => 'Welcome to the platform!',
            'type' => 'system',
        ]));

        $user->notify(new DatabaseCustomNotification([
            'title' => 'Important Alert',
            'message' => 'Please verify your details.',
            'type' => 'alert',
        ]));

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
                            'message',
                            'type',
                            'action_url',
                            'metadata',
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

        $user->notify(new DatabaseCustomNotification([
            'title' => 'Unread Message',
            'message' => 'This is unread',
        ]));

        $user->notify(new DatabaseCustomNotification([
            'title' => 'Read Message',
            'message' => 'This will be marked read',
        ]));

        $notificationToRead = $user->notifications()->first();
        $notificationToRead->markAsRead();

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

        $user->notify(new DatabaseCustomNotification([
            'title' => 'Test Notification',
            'message' => 'Read me',
        ]));

        $notification = $user->unreadNotifications()->first();

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

        $this->assertEquals(0, $user->unreadNotifications()->count());
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('user');

        $user->notify(new DatabaseCustomNotification(['title' => 'One', 'message' => 'First']));
        $user->notify(new DatabaseCustomNotification(['title' => 'Two', 'message' => 'Second']));

        $this->assertEquals(2, $user->unreadNotifications()->count());

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

        $this->assertEquals(0, $user->unreadNotifications()->count());
    }

    public function test_user_can_delete_single_notification(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('user');

        $user->notify(new DatabaseCustomNotification(['title' => 'To Delete', 'message' => 'Delete this']));
        $notification = $user->notifications()->first();

        Sanctum::actingAs($user, ['*']);

        $response = $this->deleteJson("/api/user/notifications/{$notification->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Notification deleted successfully.',
            ]);

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_user_can_clear_read_notifications(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('user');

        $user->notify(new DatabaseCustomNotification(['title' => 'Keep Unread', 'message' => 'Unread']));
        $user->notify(new DatabaseCustomNotification(['title' => 'To Clear', 'message' => 'Read']));

        $user->notifications()->where('data', 'like', '%To Clear%')->first()->markAsRead();

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

        $this->assertEquals(1, $user->notifications()->count());
        $this->assertEquals('Keep Unread', $user->notifications()->first()->data['title']);
    }

    public function test_user_cannot_access_or_modify_another_users_notification(): void
    {
        $user1 = User::factory()->create(['status' => 'active']);
        $user1->assignRole('user');

        $user2 = User::factory()->create(['status' => 'active']);
        $user2->assignRole('user');

        $user2->notify(new DatabaseCustomNotification(['title' => 'Secret', 'message' => 'For user 2 only']));
        $user2Notification = $user2->notifications()->first();

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
                'recipient_type' => 'single',
                'user_id' => $user->id,
                'title' => 'Admin Direct Message',
                'message' => 'Hello User from Admin',
                'type' => 'admin_notice',
                'action_url' => 'https://vyaparidarbar.com/dashboard',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'recipient_count' => 1,
                ],
            ]);

        $this->assertEquals(1, $user->notifications()->count());
        $notif = $user->notifications()->first();
        $this->assertEquals('Admin Direct Message', $notif->data['title']);
        $this->assertEquals('https://vyaparidarbar.com/dashboard', $notif->data['action_url']);
    }

    public function test_admin_can_broadcast_notification_to_role(): void
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
        $trader1->assignRole('trader');

        $trader2 = User::factory()->create(['status' => 'active']);
        $trader2->assignRole('trader');

        $normalUser = User::factory()->create(['status' => 'active']);
        $normalUser->assignRole('user');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/notifications/send', [
                'recipient_type' => 'role',
                'role' => 'trader',
                'title' => 'Mandi Price Update',
                'message' => 'New prices published for traders.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'recipient_count' => 2,
                ],
            ]);

        $this->assertEquals(1, $trader1->notifications()->count());
        $this->assertEquals(1, $trader2->notifications()->count());
        $this->assertEquals(0, $normalUser->notifications()->count());
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
        $activeUser1->assignRole('user');

        $activeUser2 = User::factory()->create(['status' => 'active']);
        $activeUser2->assignRole('trader');

        $inactiveUser = User::factory()->create(['status' => 'inactive']);
        $inactiveUser->assignRole('user');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/notifications/send', [
                'recipient_type' => 'all',
                'title' => 'System Maintenance Alert',
                'message' => 'Platform maintenance at midnight.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'recipient_count' => 2,
                ],
            ]);

        $this->assertEquals(1, $activeUser1->notifications()->count());
        $this->assertEquals(1, $activeUser2->notifications()->count());
        $this->assertEquals(0, $inactiveUser->notifications()->count());
    }
}
