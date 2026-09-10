<?php

namespace App\Jobs;

use App\Enums\BatchStatus;
use App\Models\NotificationBatch;
use App\Services\NotificationAudienceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessNotificationBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    /**
     * Create a new job instance.
     *
     * @param  int  $batchId
     */
    public function __construct(
        public int $batchId
    ) {
        $this->onQueue('notifications-bulk');
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationAudienceService $audienceService): void
    {
        $batch = NotificationBatch::find($this->batchId);
        if (! $batch) {
            return;
        }

        // Check cancellation
        if ($batch->status === BatchStatus::CANCELLED) {
            Log::info("ProcessNotificationBatchJob aborted: Batch #{$this->batchId} is cancelled.");

            return;
        }

        // Atomic transition from queued to processing
        $affected = DB::table('notification_batches')
            ->where('id', $this->batchId)
            ->where('status', BatchStatus::QUEUED->value)
            ->update([
                'status' => BatchStatus::PROCESSING->value,
                'started_at' => now(),
                'updated_at' => now(),
            ]);

        // If another worker already transitioned it or batch is in another status, re-check
        $batch->refresh();
        if ($batch->status !== BatchStatus::PROCESSING && $affected === 0) {
            if ($batch->status === BatchStatus::CANCELLED || $batch->status === BatchStatus::COMPLETED) {
                return;
            }
        }

        if ($batch->target_count <= 0) {
            $batch->update([
                'status' => BatchStatus::COMPLETED,
                'completed_at' => now(),
            ]);

            return;
        }

        // Chunk audience into batches of 200 and dispatch chunk jobs on notifications queue
        $chunkSize = 200;
        $audienceService->chunkAudience($batch, $chunkSize, function (array $chunkUserIds) use ($batch) {
            // Re-verify batch status before dispatching chunk
            $currentStatus = DB::table('notification_batches')->where('id', $batch->id)->value('status');
            if ($currentStatus === BatchStatus::CANCELLED->value) {
                return;
            }

            SendNotificationChunkJob::dispatch($batch->id, $chunkUserIds)->onQueue('notifications');
        });
    }
}
