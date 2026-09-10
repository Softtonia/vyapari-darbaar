<?php

namespace Tests\Feature;

use App\Enums\AudienceType;
use App\Enums\BatchStatus;
use App\Enums\NotificationType;
use App\Jobs\ProcessNotificationBatchJob;
use App\Models\NotificationBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScheduledNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduler_dispatches_due_scheduled_batches(): void
    {
        Queue::fake();

        $dueBatch = NotificationBatch::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Due Scheduled Batch',
            'body' => 'Should run now',
            'notification_type' => NotificationType::IN_APP,
            'audience_type' => AudienceType::ALL_USERS,
            'target_count' => 10,
            'status' => BatchStatus::SCHEDULED,
            'scheduled_at' => now()->subMinute(),
        ]);

        $futureBatch = NotificationBatch::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Future Scheduled Batch',
            'body' => 'Should NOT run now',
            'notification_type' => NotificationType::IN_APP,
            'audience_type' => AudienceType::ALL_USERS,
            'target_count' => 10,
            'status' => BatchStatus::SCHEDULED,
            'scheduled_at' => now()->addHour(),
        ]);

        $this->artisan('notification:process-scheduled')
            ->assertExitCode(0);

        $dueBatch->refresh();
        $futureBatch->refresh();

        $this->assertEquals(BatchStatus::QUEUED, $dueBatch->status);
        $this->assertEquals(BatchStatus::SCHEDULED, $futureBatch->status);

        Queue::assertPushed(ProcessNotificationBatchJob::class, function ($job) use ($dueBatch) {
            return $job->batchId === $dueBatch->id;
        });
    }

    public function test_scheduler_reconciles_stuck_queued_batches(): void
    {
        Queue::fake();

        $stuckBatch = NotificationBatch::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Stuck Queued Batch',
            'body' => 'Stuck for 15 mins',
            'notification_type' => NotificationType::IN_APP,
            'audience_type' => AudienceType::ALL_USERS,
            'target_count' => 5,
            'status' => BatchStatus::QUEUED,
        ]);
        $stuckBatch->updated_at = now()->subMinutes(15);
        $stuckBatch->save(['timestamps' => false]);

        $this->artisan('notification:process-scheduled')
            ->assertExitCode(0);

        Queue::assertPushed(ProcessNotificationBatchJob::class, function ($job) use ($stuckBatch) {
            return $job->batchId === $stuckBatch->id;
        });
    }
}
