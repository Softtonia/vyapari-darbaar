<?php

namespace App\Jobs;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationType;
use App\Models\NotificationDevice;
use App\Models\User;
use App\Services\Firebase\FcmService;
use App\Services\Firebase\FirebaseConfigService;
use App\Services\InAppNotificationService;
use App\Services\NotificationLogService;
use App\Services\NotificationTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendUserNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    /**
     * Create a new job instance.
     *
     * @param  int  $userId
     * @param  string  $title
     * @param  string  $body
     * @param  NotificationType|string  $type
     * @param  string|null  $imageUrl
     * @param  string|null  $clickUrl
     * @param  array<string, mixed>  $data
     * @param  int|null  $batchId
     */
    public function __construct(
        public int $userId,
        public string $title,
        public string $body,
        public NotificationType|string $type = NotificationType::PUSH_AND_IN_APP,
        public ?string $imageUrl = null,
        public ?string $clickUrl = null,
        public array $data = [],
        public ?int $batchId = null
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(
        NotificationTemplateService $templateService,
        InAppNotificationService $inAppService,
        NotificationLogService $logService,
        FcmService $fcmService,
        FirebaseConfigService $firebaseConfigService
    ): void {
        $user = User::query()->with('companies')->find($this->userId);
        if (! $user) {
            return;
        }

        $type = $this->type instanceof NotificationType ? $this->type : NotificationType::from($this->type);

        $vars = $templateService->buildUserVariables($user);
        $renderedTitle = $templateService->render($this->title, $vars);
        $renderedBody = $templateService->render($this->body, $vars);

        // 1. In-App Notification
        if ($type === NotificationType::IN_APP || $type === NotificationType::PUSH_AND_IN_APP) {
            try {
                $inAppService->createForUser(
                    $user->id,
                    $renderedTitle,
                    $renderedBody,
                    $this->imageUrl,
                    $this->clickUrl,
                    $this->data,
                    $this->batchId
                );

                $logService->recordInAppLog(
                    $this->batchId,
                    $user->id,
                    $renderedTitle,
                    $renderedBody,
                    $this->data,
                    DeliveryStatus::SENT
                );
            } catch (Throwable $e) {
                $logService->recordInAppLog(
                    $this->batchId,
                    $user->id,
                    $renderedTitle,
                    $renderedBody,
                    $this->data,
                    DeliveryStatus::FAILED,
                    'IN_APP_FAILED',
                    $e->getMessage()
                );
            }
        }

        // 2. Push Notification
        if ($type === NotificationType::PUSH || $type === NotificationType::PUSH_AND_IN_APP) {
            $setting = $firebaseConfigService->getSettings();
            if (! $setting || ! $setting->status) {
                return;
            }

            $devices = NotificationDevice::query()
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->get();

            $customData = $this->data;
            if ($this->clickUrl) {
                $customData['click_url'] = $this->clickUrl;
            }

            foreach ($devices as $device) {
                try {
                    $response = $fcmService->sendToDevice(
                        $device->fcm_token,
                        $renderedTitle,
                        $renderedBody,
                        $customData
                    );

                    $logService->recordPushLog(
                        $this->batchId,
                        $user->id,
                        $device->id,
                        $renderedTitle,
                        $renderedBody,
                        $this->data,
                        DeliveryStatus::SENT,
                        $response['name'] ?? null
                    );
                } catch (Throwable $e) {
                    $logService->recordPushLog(
                        $this->batchId,
                        $user->id,
                        $device->id,
                        $renderedTitle,
                        $renderedBody,
                        $this->data,
                        DeliveryStatus::FAILED,
                        null,
                        $e->getMessage(),
                        'Push notification failed'
                    );
                }
            }
        }
    }
}
