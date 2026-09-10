<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\NotificationTopic;
use App\Models\NotificationTopicUser;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class NotificationTopicService
{
    /**
     * Get paginated topics.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = NotificationTopic::query()
            ->with('creator:id,first_name,last_name,name')
            ->withCount('users');

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN));
        }

        $sortBy = (string) ($filters['sort_by'] ?? 'created_at');
        $allowedSorts = ['id', 'name', 'slug', 'status', 'users_count', 'created_at'];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }

        $sortDir = strtolower((string) ($filters['sort_dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 20)));

        return $query->orderBy($sortBy, $sortDir)->paginate($perPage);
    }

    /**
     * Create a topic.
     *
     * @param  array<string, mixed>  $data
     * @param  Admin|null  $admin
     * @return NotificationTopic
     */
    public function create(array $data, ?Admin $admin = null): NotificationTopic
    {
        $data['slug'] = ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['name']);
        $data['created_by'] = $admin?->id;

        return NotificationTopic::create($data);
    }

    /**
     * Update a topic.
     *
     * @param  NotificationTopic  $topic
     * @param  array<string, mixed>  $data
     * @return NotificationTopic
     */
    public function update(NotificationTopic $topic, array $data): NotificationTopic
    {
        if (! empty($data['slug'])) {
            $data['slug'] = Str::slug($data['slug']);
        }

        $topic->update($data);

        return $topic->fresh();
    }

    /**
     * Delete a topic.
     *
     * @param  NotificationTopic  $topic
     * @return bool
     */
    public function delete(NotificationTopic $topic): bool
    {
        return (bool) $topic->delete();
    }

    /**
     * Bulk add users to a topic using upsert.
     *
     * @param  NotificationTopic  $topic
     * @param  array<int, int>  $userIds
     * @return int Count of added/active members
     */
    public function addUsers(NotificationTopic $topic, array $userIds): int
    {
        $cleanIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (empty($cleanIds)) {
            return 0;
        }

        // Filter valid users
        $validUserIds = User::query()->whereIn('id', $cleanIds)->pluck('id')->all();
        if (empty($validUserIds)) {
            return 0;
        }

        $now = now();
        $rows = array_map(fn ($uid) => [
            'notification_topic_id' => $topic->id,
            'user_id' => $uid,
            'created_at' => $now,
        ], $validUserIds);

        foreach (array_chunk($rows, 500) as $chunk) {
            NotificationTopicUser::upsert($chunk, ['notification_topic_id', 'user_id'], ['created_at']);
        }

        return count($validUserIds);
    }

    /**
     * Bulk remove users from a topic using single delete query.
     *
     * @param  NotificationTopic  $topic
     * @param  array<int, int>  $userIds
     * @return int Count of removed rows
     */
    public function removeUsers(NotificationTopic $topic, array $userIds): int
    {
        $cleanIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (empty($cleanIds)) {
            return 0;
        }

        return NotificationTopicUser::query()
            ->where('notification_topic_id', $topic->id)
            ->whereIn('user_id', $cleanIds)
            ->delete();
    }
}
