<?php

namespace App\Services;

use App\Enums\AudienceType;
use App\Enums\BatchUserStatus;
use App\Models\NotificationBatch;
use App\Models\NotificationBatchUser;
use App\Models\NotificationDevice;
use App\Models\NotificationTopic;
use App\Models\NotificationTopicUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class NotificationAudienceService
{
    /**
     * Get the active users base query.
     *
     * @return Builder<User>
     */
    public function getActiveUsersQuery(): Builder
    {
        return User::query()->where('status', 'active');
    }

    /**
     * Count estimated target users for an audience specification.
     *
     * @param  AudienceType|string  $audienceType
     * @param  array<string, mixed>  $params
     * @return int
     */
    public function countEstimatedUsers(AudienceType|string $audienceType, array $params = []): int
    {
        $type = $audienceType instanceof AudienceType ? $audienceType : AudienceType::from($audienceType);

        return match ($type) {
            AudienceType::SINGLE_USER => ! empty($params['user_id']) && $this->getActiveUsersQuery()->where('id', $params['user_id'])->exists() ? 1 : 0,
            AudienceType::SELECTED_USERS => ! empty($params['user_ids']) && is_array($params['user_ids'])
                ? $this->getActiveUsersQuery()->whereIn('id', array_unique($params['user_ids']))->count()
                : 0,
            AudienceType::TOPIC => ! empty($params['topic_id'])
                ? NotificationTopicUser::query()
                    ->where('notification_topic_id', $params['topic_id'])
                    ->whereHas('user', function ($q) {
                        $q->where('status', 'active');
                    })
                    ->count()
                : 0,
            AudienceType::ALL_USERS => $this->getActiveUsersQuery()->count(),
        };
    }

    /**
     * Count estimated active push notification devices for an audience specification.
     *
     * @param  AudienceType|string  $audienceType
     * @param  array<string, mixed>  $params
     * @return int
     */
    public function countEstimatedDevices(AudienceType|string $audienceType, array $params = []): int
    {
        $type = $audienceType instanceof AudienceType ? $audienceType : AudienceType::from($audienceType);

        $deviceQuery = NotificationDevice::query()->where('is_active', true);

        return match ($type) {
            AudienceType::SINGLE_USER => ! empty($params['user_id'])
                ? $deviceQuery->where('user_id', $params['user_id'])->count()
                : 0,
            AudienceType::SELECTED_USERS => ! empty($params['user_ids']) && is_array($params['user_ids'])
                ? $deviceQuery->whereIn('user_id', array_unique($params['user_ids']))->count()
                : 0,
            AudienceType::TOPIC => ! empty($params['topic_id'])
                ? $deviceQuery->whereIn('user_id', function ($query) use ($params) {
                    $query->select('user_id')
                        ->from('notification_topic_users')
                        ->where('notification_topic_id', $params['topic_id']);
                })->count()
                : 0,
            AudienceType::ALL_USERS => $deviceQuery->whereHas('user', function ($q) {
                $q->where('status', 'active');
            })->count(),
        };
    }

    /**
     * Snapshot audience members into notification_batch_users for single_user, selected_users, topic.
     * For all_users, returns the count of active users without creating millions of static rows eagerly.
     *
     * @param  NotificationBatch  $batch
     * @param  AudienceType|string  $audienceType
     * @param  array<string, mixed>  $params
     * @return int Target count
     */
    public function snapshotAudience(NotificationBatch $batch, AudienceType|string $audienceType, array $params = []): int
    {
        $type = $audienceType instanceof AudienceType ? $audienceType : AudienceType::from($audienceType);

        switch ($type) {
            case AudienceType::SINGLE_USER:
                $userId = (int) ($params['user_id'] ?? 0);
                if ($userId <= 0) {
                    throw new InvalidArgumentException('User ID is required for single_user audience.');
                }
                NotificationBatchUser::updateOrCreate(
                    ['notification_batch_id' => $batch->id, 'user_id' => $userId],
                    ['status' => BatchUserStatus::PENDING]
                );

                return 1;

            case AudienceType::SELECTED_USERS:
                $userIds = array_values(array_unique(array_filter(array_map('intval', (array) ($params['user_ids'] ?? [])))));
                if (empty($userIds)) {
                    throw new InvalidArgumentException('User IDs list cannot be empty for selected_users audience.');
                }

                // Verify users exist
                $validUserIds = $this->getActiveUsersQuery()->whereIn('id', $userIds)->pluck('id')->all();

                $now = now();
                $rows = array_map(fn ($id) => [
                    'notification_batch_id' => $batch->id,
                    'user_id' => $id,
                    'status' => BatchUserStatus::PENDING->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $validUserIds);

                foreach (array_chunk($rows, 500) as $chunk) {
                    NotificationBatchUser::upsert($chunk, ['notification_batch_id', 'user_id'], ['status', 'updated_at']);
                }

                return count($validUserIds);

            case AudienceType::TOPIC:
                $topicId = (int) ($params['topic_id'] ?? 0);
                if ($topicId <= 0) {
                    throw new InvalidArgumentException('Topic ID is required for topic audience.');
                }

                $topic = NotificationTopic::findOrFail($topicId);
                $batch->update(['topic_id' => $topic->id]);

                // Snapshot all active users subscribed to this topic
                $userIds = NotificationTopicUser::query()
                    ->where('notification_topic_id', $topic->id)
                    ->whereHas('user', function ($q) {
                        $q->where('status', 'active');
                    })
                    ->pluck('user_id')
                    ->all();

                $now = now();
                $rows = array_map(fn ($id) => [
                    'notification_batch_id' => $batch->id,
                    'user_id' => $id,
                    'status' => BatchUserStatus::PENDING->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $userIds);

                foreach (array_chunk($rows, 500) as $chunk) {
                    NotificationBatchUser::upsert($chunk, ['notification_batch_id', 'user_id'], ['status', 'updated_at']);
                }

                return count($userIds);

            case AudienceType::ALL_USERS:
                return $this->getActiveUsersQuery()->count();
        }

        return 0;
    }

    /**
     * Chunk audience user IDs and pass each chunk of scalar IDs to the provided callback.
     *
     * @param  NotificationBatch  $batch
     * @param  int  $chunkSize
     * @param  callable(list<int>): void  $callback
     * @return void
     */
    public function chunkAudience(NotificationBatch $batch, int $chunkSize, callable $callback): void
    {
        if ($batch->audience_type === AudienceType::ALL_USERS) {
            $this->getActiveUsersQuery()
                ->select(['id'])
                ->chunkById($chunkSize, function ($users) use ($callback) {
                    $userIds = $users->pluck('id')->all();
                    if (! empty($userIds)) {
                        $callback($userIds);
                    }
                });

            return;
        }

        // For snapshotted audiences: chunk from notification_batch_users
        NotificationBatchUser::query()
            ->where('notification_batch_id', $batch->id)
            ->whereNotNull('user_id')
            ->select(['id', 'user_id'])
            ->chunkById($chunkSize, function ($batchUsers) use ($callback) {
                $userIds = $batchUsers->pluck('user_id')->all();
                if (! empty($userIds)) {
                    $callback($userIds);
                }
            });
    }
}
