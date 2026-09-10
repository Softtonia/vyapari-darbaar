<?php

namespace Tests\Feature;

use App\Enums\AudienceType;
use App\Enums\BatchStatus;
use App\Enums\BatchUserStatus;
use App\Enums\NotificationType;
use App\Jobs\ProcessNotificationBatchJob;
use App\Jobs\SendNotificationChunkJob;
use App\Models\Admin;
use App\Models\FirebaseSetting;
use App\Models\InAppNotification;
use App\Models\NotificationBatch;
use App\Models\NotificationBatchUser;
use App\Models\NotificationDevice;
use App\Models\NotificationLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Firebase\Contracts\FirebaseAccessTokenProvider;
use App\Services\Firebase\Data\FirebaseAccessToken;
use App\Services\Firebase\FcmService;
use App\Services\Firebase\FirebaseConfigService;
use App\Services\InAppNotificationService;
use App\Services\NotificationAudienceService;
use App\Services\NotificationBatchService;
use App\Services\NotificationLogService;
use App\Services\NotificationTemplateService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationBatchAndQueueTest extends TestCase
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

        // Setup active Firebase configuration
        FirebaseSetting::create([
            'id' => 1,
            'api_key' => 'fake_api_key',
            'auth_domain' => 'vyapari-test.firebaseapp.com',
            'project_id' => 'vyapari-test',
            'messaging_sender_id' => '123456789',
            'app_id' => '1:123456789:web:test',
            'vapid_key' => 'fake_vapid_key',
            'service_account_json' => json_encode([
                'type' => 'service_account',
                'project_id' => 'vyapari-test',
                'private_key' => 'fake_private_key',
                'client_email' => 'firebase@vyapari-test.iam.gserviceaccount.com',
                'token_uri' => 'https://oauth2.googleapis.com/token',
            ]),
            'status' => true,
        ]);

        $this->app->instance(FirebaseAccessTokenProvider::class, new class implements FirebaseAccessTokenProvider {
            public function getAccessToken(array $serviceAccount, string $projectId): FirebaseAccessToken
            {
                return new FirebaseAccessToken('fake-token', time() + 3600, 'test-fingerprint');
            }
        });
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Accept' => 'application/json',
        ];
    }

    public function test_chunk_job_updates_counters_and_observes_counter_invariant(): void
    {
        Http::fake([
            'https://fcm.googleapis.com/v1/projects/vyapari-test/messages:send' => Http::response(['name' => 'projects/vyapari-test/messages/msg_123'], 200),
        ]);

        $u1 = User::factory()->create(['status' => 'active']); // Will have device -> success
        $u2 = User::factory()->create(['status' => 'active']); // No device -> skipped

        NotificationDevice::create([
            'user_id' => $u1->id,
            'fcm_token' => 'fcm_token_u1',
            'fcm_token_hash' => hash('sha256', 'fcm_token_u1'),
            'device_type' => 'android',
            'is_active' => true,
        ]);

        $batch = NotificationBatch::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Broadcast Test',
            'body' => 'Hello {{user_name}}',
            'notification_type' => NotificationType::PUSH,
            'audience_type' => AudienceType::SELECTED_USERS,
            'target_count' => 2,
            'processed_count' => 0,
            'success_count' => 0,
            'partial_count' => 0,
            'failed_count' => 0,
            'skipped_count' => 0,
            'status' => BatchStatus::PROCESSING,
        ]);

        NotificationBatchUser::create(['notification_batch_id' => $batch->id, 'user_id' => $u1->id, 'status' => BatchUserStatus::PENDING]);
        NotificationBatchUser::create(['notification_batch_id' => $batch->id, 'user_id' => $u2->id, 'status' => BatchUserStatus::PENDING]);

        // Run chunk job synchronously
        $job = new SendNotificationChunkJob($batch->id, [$u1->id, $u2->id]);
        $job->handle(
            app(NotificationBatchService::class),
            app(NotificationTemplateService::class),
            app(InAppNotificationService::class),
            app(NotificationLogService::class),
            app(FcmService::class),
            app(FirebaseConfigService::class)
        );

        $batch->refresh();

        // Invariant check: processed_count = success_count + partial_count + failed_count + skipped_count
        $this->assertEquals(2, $batch->processed_count);
        $this->assertEquals(1, $batch->success_count);
        $this->assertEquals(1, $batch->skipped_count);
        $this->assertEquals(0, $batch->failed_count);
        $this->assertEquals($batch->processed_count, $batch->success_count + $batch->partial_count + $batch->failed_count + $batch->skipped_count);
        $this->assertEquals(BatchStatus::COMPLETED, $batch->status);
    }

    public function test_multi_device_push_counts_once_per_user(): void
    {
        Http::fake([
            'https://fcm.googleapis.com/v1/projects/vyapari-test/messages:send' => Http::sequence()
                ->push(['name' => 'projects/vyapari-test/messages/msg_dev1'], 200)
                ->push(['name' => 'projects/vyapari-test/messages/msg_dev2'], 200),
        ]);

        $u1 = User::factory()->create(['status' => 'active']);

        // User has 2 active devices
        NotificationDevice::create(['user_id' => $u1->id, 'fcm_token' => 'token_d1', 'fcm_token_hash' => hash('sha256', 'token_d1'), 'device_type' => 'android', 'is_active' => true]);
        NotificationDevice::create(['user_id' => $u1->id, 'fcm_token' => 'token_d2', 'fcm_token_hash' => hash('sha256', 'token_d2'), 'device_type' => 'ios', 'is_active' => true]);

        $batch = NotificationBatch::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Multi-device Test',
            'body' => 'Testing multiple devices',
            'notification_type' => NotificationType::PUSH,
            'audience_type' => AudienceType::SINGLE_USER,
            'target_count' => 1,
            'processed_count' => 0,
            'success_count' => 0,
            'status' => BatchStatus::PROCESSING,
        ]);

        NotificationBatchUser::create(['notification_batch_id' => $batch->id, 'user_id' => $u1->id, 'status' => BatchUserStatus::PENDING]);

        $job = new SendNotificationChunkJob($batch->id, [$u1->id]);
        $job->handle(
            app(NotificationBatchService::class),
            app(NotificationTemplateService::class),
            app(InAppNotificationService::class),
            app(NotificationLogService::class),
            app(FcmService::class),
            app(FirebaseConfigService::class)
        );

        $batch->refresh();

        // Exactly 1 user processed, 1 user success (not 2)
        $this->assertEquals(1, $batch->processed_count);
        $this->assertEquals(1, $batch->success_count);

        // But 2 logs recorded (one per device)
        $this->assertDatabaseCount('notification_logs', 2);
    }

    public function test_push_and_in_app_partial_outcome(): void
    {
        $u1 = User::factory()->create(['status' => 'active']); // Has no push devices -> push skipped, in-app success => Partial outcome

        $batch = NotificationBatch::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Hybrid Test',
            'body' => 'Testing push and in-app',
            'notification_type' => NotificationType::PUSH_AND_IN_APP,
            'audience_type' => AudienceType::SINGLE_USER,
            'target_count' => 1,
            'processed_count' => 0,
            'success_count' => 0,
            'partial_count' => 0,
            'status' => BatchStatus::PROCESSING,
        ]);

        NotificationBatchUser::create(['notification_batch_id' => $batch->id, 'user_id' => $u1->id, 'status' => BatchUserStatus::PENDING]);

        $job = new SendNotificationChunkJob($batch->id, [$u1->id]);
        $job->handle(
            app(NotificationBatchService::class),
            app(NotificationTemplateService::class),
            app(InAppNotificationService::class),
            app(NotificationLogService::class),
            app(FcmService::class),
            app(FirebaseConfigService::class)
        );

        $batch->refresh();

        $this->assertEquals(1, $batch->processed_count);
        $this->assertEquals(1, $batch->partial_count);
        $this->assertEquals(BatchStatus::PARTIALLY_FAILED, $batch->status);

        // In-app notification created
        $this->assertDatabaseHas('in_app_notifications', [
            'notification_batch_id' => $batch->id,
            'user_id' => $u1->id,
        ]);
    }

    public function test_cancelled_batch_aborts_chunk_job_without_sending(): void
    {
        $u1 = User::factory()->create(['status' => 'active']);

        $batch = NotificationBatch::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Cancelled Test',
            'body' => 'Should not send',
            'notification_type' => NotificationType::IN_APP,
            'audience_type' => AudienceType::SINGLE_USER,
            'target_count' => 1,
            'status' => BatchStatus::CANCELLED,
        ]);

        $job = new SendNotificationChunkJob($batch->id, [$u1->id]);
        $job->handle(
            app(NotificationBatchService::class),
            app(NotificationTemplateService::class),
            app(InAppNotificationService::class),
            app(NotificationLogService::class),
            app(FcmService::class),
            app(FirebaseConfigService::class)
        );

        // In-app was NOT created
        $this->assertDatabaseMissing('in_app_notifications', [
            'notification_batch_id' => $batch->id,
        ]);
    }

    public function test_retry_failed_creates_child_batch_preserving_original_history(): void
    {
        \Illuminate\Support\Facades\Queue::fake([ProcessNotificationBatchJob::class]);

        $u1 = User::factory()->create(['status' => 'active']);
        $u2 = User::factory()->create(['status' => 'active']);

        $originalBatch = NotificationBatch::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Original Campaign',
            'body' => 'Original Body',
            'notification_type' => NotificationType::PUSH,
            'audience_type' => AudienceType::SELECTED_USERS,
            'target_count' => 2,
            'processed_count' => 2,
            'success_count' => 1,
            'partial_count' => 0,
            'failed_count' => 1,
            'skipped_count' => 0,
            'status' => BatchStatus::PARTIALLY_FAILED,
        ]);

        NotificationBatchUser::create(['notification_batch_id' => $originalBatch->id, 'user_id' => $u1->id, 'status' => BatchUserStatus::SENT]);
        NotificationBatchUser::create(['notification_batch_id' => $originalBatch->id, 'user_id' => $u2->id, 'status' => BatchUserStatus::FAILED]);

        $response = $this->postJson("/api/admin/notifications/batches/{$originalBatch->id}/retry-failed", [], $this->authHeaders());

        $response->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.parent_batch_id', $originalBatch->id)
            ->assertJsonPath('data.target_count', 1);

        $retryBatchId = $response->json('data.id');

        // Original batch stats remain immutable
        $originalBatch->refresh();
        $this->assertEquals(2, $originalBatch->target_count);
        $this->assertEquals(1, $originalBatch->success_count);
        $this->assertEquals(1, $originalBatch->failed_count);

        // New child batch targets only failed user (u2) with pending status before worker processes
        $this->assertDatabaseHas('notification_batch_users', [
            'notification_batch_id' => $retryBatchId,
            'user_id' => $u2->id,
            'status' => 'pending',
        ]);

        \Illuminate\Support\Facades\Queue::assertPushed(ProcessNotificationBatchJob::class, function ($job) use ($retryBatchId) {
            return $job->batchId === $retryBatchId;
        });
    }
}
