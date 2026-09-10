<?php

namespace App\Services;

use App\Enums\AudienceType;
use App\Enums\BatchStatus;
use App\Enums\BatchUserStatus;
use App\Jobs\ProcessNotificationBatchJob;
use App\Models\Admin;
use App\Models\NotificationBatch;
use App\Models\NotificationBatchUser;
use App\Models\NotificationLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class NotificationBatchService
{
    /**
     * Get paginated listing of notification batches with filtering.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = NotificationBatch::query()
            ->with(['creator:id,first_name,last_name,name', 'template:id,name,code', 'topic:id,name,slug']);

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (! empty($filters['notification_type'])) {
            $query->where('notification_type', (string) $filters['notification_type']);
        }

        if (! empty($filters['audience_type'])) {
            $query->where('audience_type', (string) $filters['audience_type']);
        }

        if (! empty($filters['created_by'])) {
            $query->where('created_by', (int) $filters['created_by']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', (string) $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', (string) $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%")
                    ->orWhere('uuid', 'like', "{$search}%");
            });
        }

        $sortBy = (string) ($filters['sort_by'] ?? 'created_at');
        $allowedSorts = ['id', 'status', 'scheduled_at', 'started_at', 'completed_at', 'created_at'];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }

        $sortDir = strtolower((string) ($filters['sort_dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 20)));

        return $query->orderBy($sortBy, $sortDir)->paginate($perPage);
    }

    /**
     * Find a batch by ID or UUID.
     *
     * @param  int|string  $idOrUuid
     * @return NotificationBatch|null
     */
    public function find(int|string $idOrUuid): ?NotificationBatch
    {
        $query = NotificationBatch::query()
            ->with([
                'creator:id,first_name,last_name,name',
                'template:id,name,code',
                'topic:id,name,slug',
                'parentBatch:id,uuid,title',
                'retryBatches:id,uuid,parent_batch_id,status,target_count,success_count,failed_count,created_at',
            ]);

        if (is_numeric($idOrUuid)) {
            return $query->find((int) $idOrUuid);
        }

        return $query->where('uuid', (string) $idOrUuid)->first();
    }

    /**
     * Cancel an active or pending notification batch.
     *
     * @param  NotificationBatch  $batch
     * @return NotificationBatch
     */
    public function cancel(NotificationBatch $batch): NotificationBatch
    {
        if (in_array($batch->status, [BatchStatus::COMPLETED, BatchStatus::CANCELLED, BatchStatus::FAILED], true)) {
            throw new RuntimeException("Cannot cancel batch in '{$batch->status->value}' status.");
        }

        $batch->update([
            'status' => BatchStatus::CANCELLED,
            'completed_at' => now(),
        ]);

        return $batch->fresh();
    }

    /**
     * Retry failed recipients of a batch by creating a new child batch preserving historical metrics.
     *
     * @param  NotificationBatch  $batch
     * @param  Admin|null  $admin
     * @return NotificationBatch
     */
    public function retryFailed(NotificationBatch $batch, ?Admin $admin = null): NotificationBatch
    {
        // Identify failed or partial user IDs
        $failedUserIds = NotificationBatchUser::query()
            ->where('notification_batch_id', $batch->id)
            ->whereIn('status', [BatchUserStatus::FAILED, BatchUserStatus::PARTIAL])
            ->pluck('user_id')
            ->all();

        // If batch was all_users without batch_users rows, resolve from failed logs
        if (empty($failedUserIds)) {
            $failedUserIds = NotificationLog::query()
                ->where('notification_batch_id', $batch->id)
                ->where('status', 'failed')
                ->whereNotNull('user_id')
                ->distinct()
                ->pluck('user_id')
                ->all();
        }

        $failedUserIds = array_values(array_unique(array_filter($failedUserIds)));

        if (empty($failedUserIds)) {
            throw new RuntimeException('No failed recipients found to retry for this batch.');
        }

        return DB::transaction(function () use ($batch, $failedUserIds, $admin) {
            $retryBatch = NotificationBatch::create([
                'uuid' => (string) Str::uuid(),
                'parent_batch_id' => $batch->id,
                'template_id' => $batch->template_id,
                'topic_id' => $batch->topic_id,
                'title' => $batch->title,
                'body' => $batch->body,
                'image_url' => $batch->image_url,
                'click_url' => $batch->click_url,
                'data_json' => $batch->data_json,
                'notification_type' => $batch->notification_type,
                'audience_type' => AudienceType::SELECTED_USERS,
                'target_count' => count($failedUserIds),
                'processed_count' => 0,
                'success_count' => 0,
                'partial_count' => 0,
                'failed_count' => 0,
                'skipped_count' => 0,
                'status' => BatchStatus::QUEUED,
                'scheduled_at' => null,
                'started_at' => null,
                'completed_at' => null,
                'created_by' => $admin?->id ?? $batch->created_by,
            ]);

            $now = now();
            $rows = array_map(fn ($id) => [
                'notification_batch_id' => $retryBatch->id,
                'user_id' => $id,
                'status' => BatchUserStatus::PENDING->value,
                'created_at' => $now,
                'updated_at' => $now,
            ], $failedUserIds);

            foreach (array_chunk($rows, 500) as $chunk) {
                NotificationBatchUser::upsert($chunk, ['notification_batch_id', 'user_id'], ['status', 'updated_at']);
            }

            // Dispatch batch job on notifications-bulk queue
            ProcessNotificationBatchJob::dispatch($retryBatch->id)->onQueue('notifications-bulk');

            return $retryBatch;
        });
    }

    /**
     * Atomically increment batch progress and exactly one outcome counter for a recipient.
     * Also marks batch completion safely.
     *
     * @param  int  $batchId
     * @param  BatchUserStatus  $outcome
     * @return void
     */
    public function recordRecipientOutcome(int $batchId, BatchUserStatus $outcome): void
    {
        $column = match ($outcome) {
            BatchUserStatus::SENT => 'success_count',
            BatchUserStatus::PARTIAL => 'partial_count',
            BatchUserStatus::FAILED => 'failed_count',
            BatchUserStatus::SKIPPED => 'skipped_count',
            default => 'skipped_count',
        };

        DB::table('notification_batches')
            ->where('id', $batchId)
            ->update([
                'processed_count' => DB::raw('processed_count + 1'),
                $column => DB::raw("{$column} + 1"),
                'updated_at' => now(),
            ]);

        // Check if batch is completed
        $batch = DB::table('notification_batches')->where('id', $batchId)->first();
        if ($batch && $batch->target_count > 0 && $batch->processed_count >= $batch->target_count) {
            $finalStatus = BatchStatus::COMPLETED->value;
            if ($batch->failed_count == $batch->target_count) {
                $finalStatus = BatchStatus::FAILED->value;
            } elseif ($batch->failed_count > 0 || $batch->partial_count > 0) {
                $finalStatus = BatchStatus::PARTIALLY_FAILED->value;
            }

            DB::table('notification_batches')
                ->where('id', $batchId)
                ->whereNotIn('status', [BatchStatus::CANCELLED->value, BatchStatus::COMPLETED->value, BatchStatus::FAILED->value, BatchStatus::PARTIALLY_FAILED->value])
                ->update([
                    'status' => $finalStatus,
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);
        }
    }
}
