<?php

namespace App\Console\Commands;

use App\Enums\BatchStatus;
use App\Jobs\ProcessNotificationBatchJob;
use App\Models\NotificationBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessScheduledNotificationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:process-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch due scheduled notification batches safely with concurrency protection';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = now();

        $dueBatches = NotificationBatch::query()
            ->where('status', BatchStatus::SCHEDULED)
            ->where('scheduled_at', '<=', $now)
            ->get();

        $dispatchedCount = 0;

        foreach ($dueBatches as $batch) {
            // Atomic conditional update to prevent double dispatch across concurrent scheduler processes
            $affected = DB::table('notification_batches')
                ->where('id', $batch->id)
                ->where('status', BatchStatus::SCHEDULED->value)
                ->update([
                    'status' => BatchStatus::QUEUED->value,
                    'updated_at' => $now,
                ]);

            if ($affected > 0) {
                try {
                    ProcessNotificationBatchJob::dispatch($batch->id)->onQueue('notifications-bulk');
                    $dispatchedCount++;
                    $this->info("Scheduled batch #{$batch->id} ({$batch->uuid}) transitioned to queued and dispatched.");
                } catch (Throwable $e) {
                    // Revert to scheduled if queue dispatch throws synchronously
                    DB::table('notification_batches')
                        ->where('id', $batch->id)
                        ->where('status', BatchStatus::QUEUED->value)
                        ->update([
                            'status' => BatchStatus::SCHEDULED->value,
                            'updated_at' => now(),
                        ]);

                    Log::error("Failed to dispatch scheduled batch #{$batch->id}: " . $e->getMessage());
                    $this->error("Failed to dispatch batch #{$batch->id}: {$e->getMessage()}");
                }
            }
        }

        // Reconcile stuck queued batches (>10 min with no processing start)
        $stuckThreshold = now()->subMinutes(10);
        $stuckBatches = NotificationBatch::query()
            ->where('status', BatchStatus::QUEUED)
            ->where('updated_at', '<=', $stuckThreshold)
            ->get();

        foreach ($stuckBatches as $stuckBatch) {
            try {
                ProcessNotificationBatchJob::dispatch($stuckBatch->id)->onQueue('notifications-bulk');
                $stuckBatch->touch();
                $this->warn("Re-dispatched stuck queued batch #{$stuckBatch->id}.");
            } catch (Throwable $e) {
                Log::warning("Could not re-dispatch stuck queued batch #{$stuckBatch->id}: " . $e->getMessage());
            }
        }

        $this->info("Processed scheduled notifications. Dispatched: {$dispatchedCount}");

        return self::SUCCESS;
    }
}
