<?php

namespace App\Services;

use App\Enums\AudienceType;
use App\Enums\BatchStatus;
use App\Enums\NotificationType;
use App\Jobs\ProcessNotificationBatchJob;
use App\Models\Admin;
use App\Models\NotificationBatch;
use App\Models\NotificationTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class NotificationSendService
{
    public function __construct(
        protected NotificationAudienceService $audienceService,
        protected NotificationTemplateService $templateService
    ) {}

    /**
     * Preview notification content and estimate audience metrics without dispatching.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function preview(array $data): array
    {
        $resolved = $this->resolveContent($data);

        $sampleVars = [
            'user_name' => 'Rajesh Sharma',
            'first_name' => 'Rajesh',
            'last_name' => 'Sharma',
            'company_name' => 'Sharma Agro Traders',
            'date' => now()->format('d M Y'),
            'app_name' => config('app.name', 'Vyapari Darbaar'),
        ];

        $sampleTitle = $this->templateService->render($resolved['title'], $sampleVars);
        $sampleBody = $this->templateService->render($resolved['body'], $sampleVars);

        $audienceType = AudienceType::from($data['audience_type']);
        $estimatedUsers = $this->audienceService->countEstimatedUsers($audienceType, $data);
        $estimatedDevices = $this->audienceService->countEstimatedDevices($audienceType, $data);

        $unsupportedTitle = $this->templateService->findUnsupportedPlaceholders($resolved['title']);
        $unsupportedBody = $this->templateService->findUnsupportedPlaceholders($resolved['body']);
        $unsupported = array_values(array_unique(array_merge($unsupportedTitle, $unsupportedBody)));

        return [
            'resolved_title' => $sampleTitle,
            'resolved_body' => $sampleBody,
            'image_url' => $resolved['image_url'],
            'click_url' => $resolved['click_url'],
            'data' => $resolved['data'],
            'estimated_user_count' => $estimatedUsers,
            'estimated_active_device_count' => $estimatedDevices,
            'unsupported_placeholders' => $unsupported,
            'has_unsupported_placeholders' => ! empty($unsupported),
        ];
    }

    /**
     * Create and dispatch notification campaign batch.
     *
     * @param  array<string, mixed>  $data
     * @param  Admin|null  $admin
     * @return NotificationBatch
     */
    public function send(array $data, ?Admin $admin = null): NotificationBatch
    {
        $resolved = $this->resolveContent($data);

        // Reject send if unsupported placeholders exist
        $unsupported = array_merge(
            $this->templateService->findUnsupportedPlaceholders($resolved['title']),
            $this->templateService->findUnsupportedPlaceholders($resolved['body'])
        );
        if (! empty($unsupported)) {
            $invalidKeys = implode(', ', array_unique($unsupported));
            throw new InvalidArgumentException("Notification content contains unsupported placeholder(s): {$invalidKeys}");
        }

        $audienceType = AudienceType::from($data['audience_type']);
        $notificationType = NotificationType::from($data['notification_type']);

        $sendNow = filter_var($data['send_now'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $scheduledAt = ! empty($data['scheduled_at']) ? \Carbon\Carbon::parse($data['scheduled_at']) : null;

        $isScheduled = ! $sendNow && $scheduledAt !== null && $scheduledAt->isFuture();
        $initialStatus = $isScheduled ? BatchStatus::SCHEDULED : BatchStatus::QUEUED;

        $batch = DB::transaction(function () use ($data, $resolved, $audienceType, $notificationType, $initialStatus, $scheduledAt, $admin) {
            $batchRecord = NotificationBatch::create([
                'uuid' => (string) Str::uuid(),
                'template_id' => $data['template_id'] ?? null,
                'topic_id' => $data['topic_id'] ?? null,
                'title' => $resolved['title'],
                'body' => $resolved['body'],
                'image_url' => $resolved['image_url'],
                'click_url' => $resolved['click_url'],
                'data_json' => $resolved['data'],
                'notification_type' => $notificationType,
                'audience_type' => $audienceType,
                'target_count' => 0,
                'processed_count' => 0,
                'success_count' => 0,
                'partial_count' => 0,
                'failed_count' => 0,
                'skipped_count' => 0,
                'status' => $initialStatus,
                'scheduled_at' => $scheduledAt,
                'started_at' => null,
                'completed_at' => null,
                'created_by' => $admin?->id,
            ]);

            $targetCount = $this->audienceService->snapshotAudience($batchRecord, $audienceType, $data);
            $batchRecord->update(['target_count' => $targetCount]);

            return $batchRecord;
        });

        // Dispatch job outside DB transaction only when queued
        if ($batch->status === BatchStatus::QUEUED) {
            ProcessNotificationBatchJob::dispatch($batch->id)->onQueue('notifications-bulk');
        }

        return $batch->fresh();
    }

    /**
     * Resolve final title, body, image_url, click_url, and data with template inheritance.
     *
     * @param  array<string, mixed>  $data
     * @return array{title: string, body: string, image_url: ?string, click_url: ?string, data: ?array}
     */
    protected function resolveContent(array $data): array
    {
        $template = null;
        if (! empty($data['template_id'])) {
            $template = NotificationTemplate::find($data['template_id']);
        }

        $title = ! empty($data['title']) ? (string) $data['title'] : ($template?->title ?? '');
        $body = ! empty($data['body']) ? (string) $data['body'] : ($template?->body ?? '');
        $imageUrl = array_key_exists('image_url', $data) ? $data['image_url'] : ($template?->image_url ?? null);
        $clickUrl = array_key_exists('click_url', $data) ? $data['click_url'] : ($template?->click_url ?? null);
        $customData = array_key_exists('data', $data) ? $data['data'] : ($template?->data_json ?? null);

        if (trim($title) === '' || trim($body) === '') {
            throw new InvalidArgumentException('Notification title and body cannot be empty.');
        }

        return [
            'title' => $title,
            'body' => $body,
            'image_url' => $imageUrl,
            'click_url' => $clickUrl,
            'data' => is_array($customData) ? $customData : null,
        ];
    }
}
