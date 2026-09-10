<?php

namespace Tests\Feature;

use App\Jobs\ProcessNotificationBatchJob;
use App\Models\Admin;
use App\Models\NotificationDevice;
use App\Models\NotificationTemplate;
use App\Models\NotificationTopic;
use App\Models\NotificationTopicUser;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationSendAndPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->admin = Admin::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@vyaparidarbar.com',
            'password' => bcrypt('Password@123'),
            'status' => 'active',
        ]);
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'admin')->first();
        if ($adminRole) {
            $this->admin->assignRole($adminRole);
        }

        $this->adminToken = $this->admin->createToken('admin-token', ['*'])->plainTextToken;
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Accept' => 'application/json',
        ];
    }

    public function test_preview_calculates_audience_and_renders_sample(): void
    {
        $u1 = User::factory()->create(['status' => 'active']);
        $u2 = User::factory()->create(['status' => 'active']);

        NotificationDevice::create([
            'user_id' => $u1->id,
            'fcm_token' => 'token_1',
            'fcm_token_hash' => hash('sha256', 'token_1'),
            'device_type' => 'android',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/admin/notifications/preview', [
            'audience_type' => 'selected_users',
            'user_ids' => [$u1->id, $u2->id],
            'title' => 'Special Update for {{user_name}}',
            'body' => 'Welcome to {{company_name}}!',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.estimated_user_count', 2)
            ->assertJsonPath('data.estimated_active_device_count', 1)
            ->assertJsonPath('data.has_unsupported_placeholders', false);
    }

    public function test_preview_flags_unsupported_placeholders(): void
    {
        $response = $this->postJson('/api/admin/notifications/preview', [
            'audience_type' => 'all_users',
            'title' => 'Order {{order_id}} shipped',
            'body' => 'Tracking {{tracking_number}}',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.has_unsupported_placeholders', true)
            ->assertJsonCount(2, 'data.unsupported_placeholders');
    }

    public function test_send_rejects_unsupported_placeholders(): void
    {
        $response = $this->postJson('/api/admin/notifications/send', [
            'notification_type' => 'push',
            'audience_type' => 'all_users',
            'title' => 'Order {{order_id}} status',
            'body' => 'Your order is processed',
            'send_now' => true,
        ], $this->authHeaders());

        $response->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('error', 'INVALID_CAMPAIGN_DATA');
    }

    public function test_send_snapshots_single_user_and_dispatches_job(): void
    {
        Queue::fake();

        $user = User::factory()->create(['status' => 'active']);

        $response = $this->postJson('/api/admin/notifications/send', [
            'notification_type' => 'push_and_in_app',
            'audience_type' => 'single_user',
            'user_id' => $user->id,
            'title' => 'Important Alert',
            'body' => 'Hello {{user_name}}, please verify your account.',
            'send_now' => true,
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.target_count', 1)
            ->assertJsonPath('data.status', 'queued');

        $batchId = $response->json('data.id');

        $this->assertDatabaseHas('notification_batch_users', [
            'notification_batch_id' => $batchId,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        Queue::assertPushedOn('notifications-bulk', ProcessNotificationBatchJob::class);
    }

    public function test_send_snapshots_topic_audience(): void
    {
        Queue::fake();

        $topic = NotificationTopic::create(['name' => 'Grain Traders', 'slug' => 'grain-traders']);
        $u1 = User::factory()->create(['status' => 'active']);
        $u2 = User::factory()->create(['status' => 'active']);

        NotificationTopicUser::create(['notification_topic_id' => $topic->id, 'user_id' => $u1->id, 'created_at' => now()]);
        NotificationTopicUser::create(['notification_topic_id' => $topic->id, 'user_id' => $u2->id, 'created_at' => now()]);

        $response = $this->postJson('/api/admin/notifications/send', [
            'notification_type' => 'in_app',
            'audience_type' => 'topic',
            'topic_id' => $topic->id,
            'title' => 'Grain Market Rates',
            'body' => 'Rates updated today.',
            'send_now' => true,
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.target_count', 2)
            ->assertJsonPath('data.topic.id', $topic->id);

        $batchId = $response->json('data.id');
        $this->assertDatabaseCount('notification_batch_users', 2);
    }

    public function test_send_schedules_future_batch_without_immediate_queue(): void
    {
        Queue::fake();

        $user = User::factory()->create(['status' => 'active']);
        $future = now()->addHours(2)->toDateTimeString();

        $response = $this->postJson('/api/admin/notifications/send', [
            'notification_type' => 'push',
            'audience_type' => 'single_user',
            'user_id' => $user->id,
            'title' => 'Future Announcement',
            'body' => 'This is scheduled.',
            'send_now' => false,
            'scheduled_at' => $future,
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.status', 'scheduled');

        Queue::assertNothingPushed();
    }
}
