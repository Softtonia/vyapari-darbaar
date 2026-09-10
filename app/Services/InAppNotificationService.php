<?php

namespace App\Services;

use App\Models\InAppNotification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InAppNotificationService
{
    /**
     * Get paginated notifications for the authenticated user.
     *
     * @param  User  $user
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator
     */
    public function paginateForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = InAppNotification::query()->where('user_id', $user->id);

        $status = strtolower((string) ($filters['status'] ?? 'all'));
        if ($status === 'unread') {
            $query->unread();
        } elseif ($status === 'read') {
            $query->read();
        }

        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 20)));

        return $query->latest('id')->paginate($perPage);
    }

    /**
     * Get count of unread notifications for a user.
     *
     * @param  User  $user
     * @return int
     */
    public function unreadCountForUser(User $user): int
    {
        return InAppNotification::query()
            ->where('user_id', $user->id)
            ->unread()
            ->count();
    }

    /**
     * Mark a specific notification as read for a user (strictly scoped to owner).
     *
     * @param  User  $user
     * @param  int  $id
     * @return InAppNotification|null
     */
    public function markAsReadForUser(User $user, int $id): ?InAppNotification
    {
        $notification = InAppNotification::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (! $notification) {
            return null;
        }

        $notification->markAsRead();

        return $notification->fresh();
    }

    /**
     * Mark all unread notifications as read for a user.
     *
     * @param  User  $user
     * @return int Number of updated rows
     */
    public function markAllAsReadForUser(User $user): int
    {
        return InAppNotification::query()
            ->where('user_id', $user->id)
            ->unread()
            ->update(['read_at' => now()]);
    }

    /**
     * Delete a single notification for a user (strictly scoped to owner).
     *
     * @param  User  $user
     * @param  int  $id
     * @return bool
     */
    public function deleteForUser(User $user, int $id): bool
    {
        return (bool) InAppNotification::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->delete();
    }

    /**
     * Clear all read notifications for a user.
     *
     * @param  User  $user
     * @return int
     */
    public function clearReadForUser(User $user): int
    {
        return InAppNotification::query()
            ->where('user_id', $user->id)
            ->read()
            ->delete();
    }

    /**
     * Create an in-app notification record safely and idempotently.
     *
     * @param  int  $userId
     * @param  string  $title
     * @param  string  $body
     * @param  string|null  $imageUrl
     * @param  string|null  $clickUrl
     * @param  array<string, mixed>|null  $dataJson
     * @param  int|null  $batchId
     * @return InAppNotification
     */
    public function createForUser(
        int $userId,
        string $title,
        string $body,
        ?string $imageUrl = null,
        ?string $clickUrl = null,
        ?array $dataJson = null,
        ?int $batchId = null
    ): InAppNotification {
        if ($batchId !== null) {
            return InAppNotification::firstOrCreate(
                [
                    'notification_batch_id' => $batchId,
                    'user_id' => $userId,
                ],
                [
                    'title' => $title,
                    'body' => $body,
                    'image_url' => $imageUrl,
                    'click_url' => $clickUrl,
                    'data_json' => $dataJson,
                ]
            );
        }

        return InAppNotification::create([
            'user_id' => $userId,
            'notification_batch_id' => null,
            'title' => $title,
            'body' => $body,
            'image_url' => $imageUrl,
            'click_url' => $clickUrl,
            'data_json' => $dataJson,
        ]);
    }

    /**
     * Get paginated in-app notifications for Admin inspection.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator
     */
    public function paginateForAdmin(array $filters = []): LengthAwarePaginator
    {
        $query = InAppNotification::query()
            ->with(['user:id,first_name,last_name,name,email,username', 'batch:id,uuid,title']);

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['batch_id'])) {
            $query->where('notification_batch_id', (int) $filters['batch_id']);
        }

        if (isset($filters['is_read']) && $filters['is_read'] !== '') {
            $isRead = filter_var($filters['is_read'], FILTER_VALIDATE_BOOLEAN);
            $isRead ? $query->read() : $query->unread();
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
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 20)));

        return $query->latest('id')->paginate($perPage);
    }

    /**
     * Find in-app notification by ID for Admin.
     *
     * @param  int  $id
     * @return InAppNotification|null
     */
    public function findForAdmin(int $id): ?InAppNotification
    {
        return InAppNotification::query()
            ->with(['user:id,first_name,last_name,name,email,username', 'batch:id,uuid,title'])
            ->find($id);
    }
}
