<?php

namespace App\Jobs;

use App\Enums\BatchStatus;
use App\Enums\BatchUserStatus;
use App\Enums\DeliveryStatus;
use App\Enums\NotificationType;
use App\Models\NotificationBatch;
use App\Models\NotificationBatchUser;
use App\Models\NotificationDevice;
use App\Models\User;
use App\Services\Firebase\FcmService;
use App\Services\Firebase\FirebaseConfigService;
use App\Services\InAppNotificationService;
use App\Services\NotificationBatchService;
use App\Services\NotificationLogService;
use App\Services\NotificationTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendNotificationChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 180;

    /**
     * Create a new job instance.
     *
     * @param  int  $batchId
     * @param  list<int>  $userIds
     */
    public function __construct(
        public int $batchId,
        public array $userIds
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(
        NotificationBatchService $batchService,
        NotificationTemplateService $templateService,
        InAppNotificationService $inAppService,
        NotificationLogService $logService,
        FcmService $fcmService,
        FirebaseConfigService $firebaseConfigService
    ): void {
        $batch = NotificationBatch::find($this->batchId);
        if (! $batch) {
            return;
        }

        // Abort immediately if batch was cancelled
        if ($batch->status === BatchStatus::CANCELLED) {
            Log::info("SendNotificationChunkJob aborted: Batch #{$this->batchId} is cancelled.");

            return;
        }

        $users = User::query()
            ->with(['companies'])
            ->whereIn('id', $this->userIds)
            ->get()
            ->keyBy('id');

        $firebaseSetting = $firebaseConfigService->getSettings();
        $isFirebaseActive = (bool) ($firebaseSetting && $firebaseSetting->status);

        foreach ($this->userIds as $userId) {
            // Check cancellation per recipient to stop fast
            $isCancelled = DB::table('notification_batches')
                ->where('id', $this->batchId)
                ->where('status', BatchStatus::CANCELLED->value)
                ->exists();

            if ($isCancelled) {
                return;
            }

            /** @var User|null $user */
            $user = $users->get($userId);
            if (! $user) {
                // User not found
                $batchService->recordRecipientOutcome($this->batchId, BatchUserStatus::SKIPPED);
                NotificationBatchUser::where('notification_batch_id', $this->batchId)
                    ->where('user_id', $userId)
                    ->update(['status' => BatchUserStatus::SKIPPED]);
                continue;
            }

            $userVars = $templateService->buildUserVariables($user);
            $renderedTitle = $templateService->render($batch->title, $userVars);
            $renderedBody = $templateService->render($batch->body, $userVars);
            $customData = $batch->data_json ?? [];
            if ($batch->click_url) {
                $customData['click_url'] = $batch->click_url;
            }

            $inAppOutcome = null; // null, 'sent', 'failed'
            $pushOutcome = null;  // null, 'sent', 'failed', 'skipped'

            // 1. Process In-App Delivery
            if ($batch->notification_type === NotificationType::IN_APP || $batch->notification_type === NotificationType::PUSH_AND_IN_APP) {
                try {
                    $inAppService->createForUser(
                        $user->id,
                        $renderedTitle,
                        $renderedBody,
                        $batch->image_url,
                        $batch->click_url,
                        $batch->data_json,
                        $batch->id
                    );

                    $logService->recordInAppLog(
                        $batch->id,
                        $user->id,
                        $renderedTitle,
                        $renderedBody,
                        $batch->data_json,
                        DeliveryStatus::SENT
                    );

                    $inAppOutcome = 'sent';
                } catch (Throwable $e) {
                    $logService->recordInAppLog(
                        $batch->id,
                        $user->id,
                        $renderedTitle,
                        $renderedBody,
                        $batch->data_json,
                        DeliveryStatus::FAILED,
                        'IN_APP_SAVE_ERROR',
                        $e->getMessage()
                    );

                    $inAppOutcome = 'failed';
                }
            }

            // 2. Process Push Delivery
            if ($batch->notification_type === NotificationType::PUSH || $batch->notification_type === NotificationType::PUSH_AND_IN_APP) {
                if (! $isFirebaseActive) {
                    $logService->recordPushLog(
                        $batch->id,
                        $user->id,
                        null,
                        $renderedTitle,
                        $renderedBody,
                        $batch->data_json,
                        DeliveryStatus::SKIPPED,
                        null,
                        'FIREBASE_DISABLED',
                        'Firebase is disabled or not configured.'
                    );
                    $pushOutcome = 'skipped';
                } else {
                    $devices = NotificationDevice::query()
                        ->where('user_id', $user->id)
                        ->where('is_active', true)
                        ->get();

                    if ($devices->isEmpty()) {
                        $logService->recordPushLog(
                            $batch->id,
                            $user->id,
                            null,
                            $renderedTitle,
                            $renderedBody,
                            $batch->data_json,
                            DeliveryStatus::SKIPPED,
                            null,
                            'NO_ACTIVE_DEVICES',
                            'No active push devices registered for this user.'
                        );
                        $pushOutcome = 'skipped';
                    } else {
                        $deviceSuccessCount = 0;
                        foreach ($devices as $device) {
                            try {
                                $response = $fcmService->sendToDevice(
                                    $device->fcm_token,
                                    $renderedTitle,
                                    $renderedBody,
                                    $customData
                                );

                                $messageId = $response['name'] ?? null;

                                $logService->recordPushLog(
                                    $batch->id,
                                    $user->id,
                                    $device->id,
                                    $renderedTitle,
                                    $renderedBody,
                                    $batch->data_json,
                                    DeliveryStatus::SENT,
                                    $messageId
                                );

                                $deviceSuccessCount++;
                            } catch (Throwable $e) {
                                $logService->recordPushLog(
                                    $batch->id,
                                    $user->id,
                                    $device->id,
                                    $renderedTitle,
                                    $renderedBody,
                                    $batch->data_json,
                                    DeliveryStatus::FAILED,
                                    null,
                                    $e->getMessage(),
                                    'FCM send failed'
                                );
                            }
                        }

                        $pushOutcome = $deviceSuccessCount > 0 ? 'sent' : 'failed';
                    }
                }
            }

            // 3. Resolve Composite Recipient Outcome
            $finalOutcome = BatchUserStatus::SKIPPED;

            if ($batch->notification_type === NotificationType::IN_APP) {
                $finalOutcome = $inAppOutcome === 'sent' ? BatchUserStatus::SENT : BatchUserStatus::FAILED;
            } elseif ($batch->notification_type === NotificationType::PUSH) {
                $finalOutcome = match ($pushOutcome) {
                    'sent' => BatchUserStatus::SENT,
                    'failed' => BatchUserStatus::FAILED,
                    default => BatchUserStatus::SKIPPED,
                };
            } elseif ($batch->notification_type === NotificationType::PUSH_AND_IN_APP) {
                if ($inAppOutcome === 'sent' && $pushOutcome === 'sent') {
                    $finalOutcome = BatchUserStatus::SENT;
                } elseif ($inAppOutcome === 'failed' && $pushOutcome === 'failed') {
                    $finalOutcome = BatchUserStatus::FAILED;
                } elseif ($inAppOutcome === 'sent' || $pushOutcome === 'sent') {
                    $finalOutcome = BatchUserStatus::PARTIAL;
                } elseif ($pushOutcome === 'skipped' && $inAppOutcome === 'sent') {
                    $finalOutcome = BatchUserStatus::PARTIAL;
                } else {
                    $finalOutcome = BatchUserStatus::SKIPPED;
                }
            }

            // Update user status and atomic batch statistics
            NotificationBatchUser::where('notification_batch_id', $this->batchId)
                ->where('user_id', $user->id)
                ->update(['status' => $finalOutcome]);

            $batchService->recordRecipientOutcome($this->batchId, $finalOutcome);
        }
    }
}
