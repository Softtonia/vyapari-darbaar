<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Models\NotificationLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationLogService
{
    /**
     * Get paginated logs for Admin inspection.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = NotificationLog::query()
            ->with([
                'user:id,first_name,last_name,name,email,username',
                'device:id,device_type,device_name,browser,ip_address',
                'batch:id,uuid,title',
            ]);

        if (! empty($filters['batch_id'])) {
            $query->where('notification_batch_id', (int) $filters['batch_id']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (! empty($filters['channel'])) {
            $query->where('channel', (string) $filters['channel']);
        }

        if (! empty($filters['error_code'])) {
            $query->where('error_code', (string) $filters['error_code']);
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
                    ->orWhere('provider_message_id', 'like', "%{$search}%")
                    ->orWhere('error_code', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $sortBy = (string) ($filters['sort_by'] ?? 'created_at');
        $allowedSorts = ['id', 'status', 'channel', 'sent_at', 'failed_at', 'created_at'];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }

        $sortDir = strtolower((string) ($filters['sort_dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 20)));

        return $query->orderBy($sortBy, $sortDir)->paginate($perPage);
    }

    /**
     * Find log by ID.
     *
     * @param  int  $id
     * @return NotificationLog|null
     */
    public function find(int $id): ?NotificationLog
    {
        return NotificationLog::query()
            ->with([
                'user:id,first_name,last_name,name,email,username',
                'device:id,device_type,device_name,browser,ip_address',
                'batch:id,uuid,title',
            ])
            ->find($id);
    }

    /**
     * Record push notification log with deduplication.
     *
     * @param  int|null  $batchId
     * @param  int|null  $userId
     * @param  int|null  $deviceId
     * @param  string  $title
     * @param  string  $body
     * @param  array<string, mixed>|null  $dataJson
     * @param  DeliveryStatus  $status
     * @param  string|null  $providerMessageId
     * @param  string|null  $errorCode
     * @param  string|null  $errorMessage
     * @return NotificationLog
     */
    public function recordPushLog(
        ?int $batchId,
        ?int $userId,
        ?int $deviceId,
        string $title,
        string $body,
        ?array $dataJson,
        DeliveryStatus $status,
        ?string $providerMessageId = null,
        ?string $errorCode = null,
        ?string $errorMessage = null
    ): NotificationLog {
        $dedupeKey = $batchId
            ? hash('sha256', "batch:{$batchId}:user:{$userId}:device:{$deviceId}:push")
            : hash('sha256', 'push:' . uniqid('', true) . ":{$userId}:{$deviceId}");

        $attributes = [
            'notification_batch_id' => $batchId,
            'user_id' => $userId,
            'notification_device_id' => $deviceId,
            'channel' => 'push',
            'title' => $title,
            'body' => $body,
            'data_json' => $dataJson,
            'status' => $status,
            'provider_message_id' => $providerMessageId,
            'error_code' => $errorCode,
            'error_message' => $errorMessage ? substr($errorMessage, 0, 1000) : null,
            'sent_at' => $status === DeliveryStatus::SENT ? now() : null,
            'failed_at' => $status === DeliveryStatus::FAILED ? now() : null,
        ];

        return NotificationLog::updateOrCreate(['dedupe_key' => $dedupeKey], $attributes);
    }

    /**
     * Record in-app notification log with deduplication.
     *
     * @param  int|null  $batchId
     * @param  int|null  $userId
     * @param  string  $title
     * @param  string  $body
     * @param  array<string, mixed>|null  $dataJson
     * @param  DeliveryStatus  $status
     * @param  string|null  $errorCode
     * @param  string|null  $errorMessage
     * @return NotificationLog
     */
    public function recordInAppLog(
        ?int $batchId,
        ?int $userId,
        string $title,
        string $body,
        ?array $dataJson,
        DeliveryStatus $status,
        ?string $errorCode = null,
        ?string $errorMessage = null
    ): NotificationLog {
        $dedupeKey = $batchId
            ? hash('sha256', "batch:{$batchId}:user:{$userId}:in_app")
            : hash('sha256', 'in_app:' . uniqid('', true) . ":{$userId}");

        $attributes = [
            'notification_batch_id' => $batchId,
            'user_id' => $userId,
            'notification_device_id' => null,
            'channel' => 'in_app',
            'title' => $title,
            'body' => $body,
            'data_json' => $dataJson,
            'status' => $status,
            'provider_message_id' => null,
            'error_code' => $errorCode,
            'error_message' => $errorMessage ? substr($errorMessage, 0, 1000) : null,
            'sent_at' => $status === DeliveryStatus::SENT ? now() : null,
            'failed_at' => $status === DeliveryStatus::FAILED ? now() : null,
        ];

        return NotificationLog::updateOrCreate(['dedupe_key' => $dedupeKey], $attributes);
    }
}
