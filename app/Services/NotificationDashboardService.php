<?php

namespace App\Services;

use App\Enums\BatchStatus;
use App\Enums\DeliveryStatus;
use App\Models\InAppNotification;
use App\Models\NotificationBatch;
use App\Models\NotificationDevice;
use App\Models\NotificationLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NotificationDashboardService
{
    public const CACHE_KEY = 'admin:notifications:dashboard';
    public const CACHE_TTL = 60; // 60 seconds

    /**
     * Get aggregated dashboard statistics (cached in Redis).
     *
     * @param  int  $days
     * @param  bool  $bypassCache
     * @return array<string, mixed>
     */
    public function getDashboardStats(int $days = 30, bool $bypassCache = false): array
    {
        $cacheKey = self::CACHE_KEY . ":days_{$days}";

        if ($bypassCache) {
            return $this->computeDashboardStats($days);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($days) {
            return $this->computeDashboardStats($days);
        });
    }

    /**
     * Invalidate dashboard Redis cache.
     *
     * @return void
     */
    public function invalidateCache(): void
    {
        Cache::forget(self::CACHE_KEY . ':days_7');
        Cache::forget(self::CACHE_KEY . ':days_30');
    }

    /**
     * Compute aggregated dashboard metrics directly from the database.
     *
     * @param  int  $days
     * @return array<string, mixed>
     */
    protected function computeDashboardStats(int $days = 30): array
    {
        $todayStart = Carbon::today()->startOfDay();

        $sentToday = NotificationLog::query()
            ->where('status', DeliveryStatus::SENT)
            ->where('created_at', '>=', $todayStart)
            ->count();

        $failedToday = NotificationLog::query()
            ->where('status', DeliveryStatus::FAILED)
            ->where('created_at', '>=', $todayStart)
            ->count();

        $activeDevices = NotificationDevice::query()
            ->where('is_active', true)
            ->count();

        $usersWithPush = NotificationDevice::query()
            ->where('is_active', true)
            ->distinct('user_id')
            ->count('user_id');

        $unreadInApp = InAppNotification::query()
            ->whereNull('read_at')
            ->count();

        $scheduledBatches = NotificationBatch::query()
            ->where('status', BatchStatus::SCHEDULED)
            ->count();

        $processingBatches = NotificationBatch::query()
            ->where('status', BatchStatus::PROCESSING)
            ->count();

        $failedBatches = NotificationBatch::query()
            ->where('status', BatchStatus::FAILED)
            ->count();

        $recentBatches = NotificationBatch::query()
            ->with('creator:id,first_name,last_name,name')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'uuid' => $b->uuid,
                'title' => $b->title,
                'notification_type' => $b->notification_type->value,
                'audience_type' => $b->audience_type->value,
                'status' => $b->status->value,
                'target_count' => $b->target_count,
                'processed_count' => $b->processed_count,
                'success_count' => $b->success_count,
                'partial_count' => $b->partial_count,
                'failed_count' => $b->failed_count,
                'skipped_count' => $b->skipped_count,
                'progress_percentage' => $b->progress_percentage,
                'created_at' => $b->created_at?->toISOString(),
            ])
            ->toArray();

        $recentFailures = NotificationLog::query()
            ->with(['user:id,first_name,last_name,name,email', 'device:id,device_type,device_name'])
            ->where('status', DeliveryStatus::FAILED)
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'batch_id' => $l->notification_batch_id,
                'user' => $l->user ? [
                    'id' => $l->user->id,
                    'name' => $l->user->name,
                    'email' => $l->user->email,
                ] : null,
                'channel' => $l->channel,
                'error_code' => $l->error_code,
                'error_message' => $l->error_message,
                'failed_at' => $l->failed_at?->toISOString() ?? $l->created_at?->toISOString(),
            ])
            ->toArray();

        // Chart data for last $days
        $startDate = Carbon::today()->subDays($days - 1)->startOfDay();

        $logStats = NotificationLog::query()
            ->selectRaw("DATE(created_at) as date, status, COUNT(*) as count")
            ->where('created_at', '>=', $startDate)
            ->groupBy('date', 'status')
            ->get();

        $chartMap = [];
        for ($i = 0; $i < $days; $i++) {
            $d = Carbon::today()->subDays($days - 1 - $i)->format('Y-m-d');
            $chartMap[$d] = [
                'date' => $d,
                'sent' => 0,
                'failed' => 0,
            ];
        }

        foreach ($logStats as $stat) {
            $d = (string) $stat->date;
            $statusVal = $stat->status instanceof DeliveryStatus ? $stat->status->value : (string) $stat->status;
            if (isset($chartMap[$d])) {
                if ($statusVal === DeliveryStatus::SENT->value) {
                    $chartMap[$d]['sent'] = (int) $stat->count;
                } elseif ($statusVal === DeliveryStatus::FAILED->value) {
                    $chartMap[$d]['failed'] = (int) $stat->count;
                }
            }
        }

        // Device platform distribution
        $platforms = NotificationDevice::query()
            ->selectRaw("device_type, COUNT(*) as count")
            ->where('is_active', true)
            ->groupBy('device_type')
            ->pluck('count', 'device_type')
            ->toArray();

        $deviceDistribution = [
            'android' => (int) ($platforms['android'] ?? 0),
            'ios' => (int) ($platforms['ios'] ?? 0),
            'web' => (int) ($platforms['web'] ?? 0),
            'other' => (int) ($platforms['other'] ?? 0),
        ];

        return [
            'sent_today' => $sentToday,
            'failed_today' => $failedToday,
            'active_devices' => $activeDevices,
            'users_with_push' => $usersWithPush,
            'unread_in_app' => $unreadInApp,
            'scheduled_batches' => $scheduledBatches,
            'processing_batches' => $processingBatches,
            'failed_batches' => $failedBatches,
            'recent_batches' => $recentBatches,
            'recent_failures' => $recentFailures,
            'chart_data' => array_values($chartMap),
            'device_platform_distribution' => $deviceDistribution,
        ];
    }
}
