<?php

namespace App\Jobs;

use App\Services\Firebase\FcmService;
use App\Services\Firebase\FirebaseConfigService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendFcmNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public int $timeout = 60;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Create a new job instance.
     * Only scalar identifiers and message payloads are stored in the job.
     *
     * @param  int  $userId
     * @param  string  $title
     * @param  string  $body
     * @param  array<string, string>  $data
     */
    public function __construct(
        public int $userId,
        public string $title,
        public string $body,
        public array $data = []
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(FcmService $fcmService, FirebaseConfigService $configService): void
    {
        $setting = $configService->getSettings();
        if (! $setting || ! $setting->status) {
            Log::info('Skipping queued FCM notification: Firebase is disabled or unconfigured.', [
                'user_id' => $this->userId,
            ]);

            return;
        }

        $fcmService->sendToUser($this->userId, $this->title, $this->body, $this->data);
    }
}
