<?php

namespace App\Services\Firebase;

use App\Models\NotificationDevice;
use App\Models\User;
use App\Services\Firebase\Contracts\FirebaseAccessTokenProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FcmService
{
    public function __construct(
        protected FirebaseConfigService $configService,
        protected FirebaseAccessTokenProvider $tokenProvider,
        protected NotificationDeviceService $deviceService
    ) {}

    /**
     * Send a test FCM notification using the saved Firebase configuration.
     * Note: Configuration does not need to be active (status = true) for admin tests.
     *
     * @param  string  $plainFcmToken
     * @param  string|null  $title
     * @param  string|null  $body
     * @return array<string, mixed>
     */
    public function sendTestNotification(
        string $plainFcmToken,
        ?string $title = null,
        ?string $body = null
    ): array {
        $setting = $this->configService->getSettings();
        if (! $setting) {
            throw new RuntimeException('FIREBASE_CONFIGURATION_MISSING');
        }

        $serviceAccount = $this->configService->getDecryptedServiceAccount();
        if (! $serviceAccount) {
            throw new RuntimeException('FIREBASE_CONFIGURATION_MISSING');
        }

        $title = ! empty($title) ? $title : 'Vyapari Darbaar Test Notification';
        $body = ! empty($body) ? $body : 'Firebase notification configuration is working.';

        return $this->sendHttpV1Message(
            $setting->project_id,
            $serviceAccount,
            $plainFcmToken,
            $title,
            $body,
            ['type' => 'test_notification']
        );
    }

    /**
     * Send an FCM notification to a specific plain device token.
     * Requires active Firebase configuration (status = true).
     *
     * @param  string  $plainFcmToken
     * @param  string  $title
     * @param  string  $body
     * @param  array<string, string>  $data
     * @return array<string, mixed>
     */
    public function sendToDevice(
        string $plainFcmToken,
        string $title,
        string $body,
        array $data = []
    ): array {
        $setting = $this->configService->getSettings();
        if (! $setting || ! $setting->status) {
            throw new RuntimeException('FIREBASE_NOT_AVAILABLE');
        }

        $serviceAccount = $this->configService->getDecryptedServiceAccount();
        if (! $serviceAccount) {
            throw new RuntimeException('FIREBASE_CONFIGURATION_MISSING');
        }

        return $this->sendHttpV1Message(
            $setting->project_id,
            $serviceAccount,
            $plainFcmToken,
            $title,
            $body,
            $data
        );
    }

    /**
     * Send an FCM notification to all active devices belonging to a user.
     * Loads only essential fields (id, fcm_token, fcm_token_hash) for performance.
     *
     * @param  User|int  $user
     * @param  string  $title
     * @param  string  $body
     * @param  array<string, string>  $data
     * @return array<string, mixed>
     */
    public function sendToUser(
        User|int $user,
        string $title,
        string $body,
        array $data = []
    ): array {
        $userId = $user instanceof User ? $user->id : $user;

        $devices = NotificationDevice::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->select(['id', 'fcm_token', 'fcm_token_hash'])
            ->get();

        if ($devices->isEmpty()) {
            return [
                'sent_count' => 0,
                'devices_count' => 0,
            ];
        }

        $sentCount = 0;
        foreach ($devices as $device) {
            try {
                $this->sendToDevice($device->fcm_token, $title, $body, $data);
                $sentCount++;
            } catch (\Throwable $e) {
                // Log sanitized error without exposing tokens or credentials
                Log::warning('Failed to send FCM notification to device', [
                    'device_id' => $device->id,
                    'error_code' => $e->getMessage(),
                ]);
            }
        }

        return [
            'sent_count' => $sentCount,
            'devices_count' => $devices->count(),
        ];
    }

    /**
     * Execute HTTP v1 request to Google FCM API.
     *
     * @param  string  $projectId
     * @param  array<string, mixed>  $serviceAccount
     * @param  string  $plainFcmToken
     * @param  string  $title
     * @param  string  $body
     * @param  array<string, string>  $data
     * @return array<string, mixed>
     */
    protected function sendHttpV1Message(
        string $projectId,
        array $serviceAccount,
        string $plainFcmToken,
        string $title,
        string $body,
        array $data = []
    ): array {
        try {
            $accessTokenObj = $this->tokenProvider->getAccessToken($serviceAccount, $projectId);
            $bearerToken = $accessTokenObj->token;
        } catch (\Throwable $e) {
            Log::error('Firebase authentication failed', [
                'project_id' => $projectId,
                'message' => $e->getMessage(),
            ]);
            throw new RuntimeException('FIREBASE_AUTH_FAILED', 0, $e);
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $stringData = [];
        foreach ($data as $k => $v) {
            $stringData[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v);
        }

        $payload = [
            'message' => [
                'token' => $plainFcmToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
            ],
        ];

        if (! empty($stringData)) {
            $payload['message']['data'] = $stringData;
        }

        try {
            /** @var Response $response */
            $response = Http::withToken($bearerToken)
                ->connectTimeout(5)
                ->timeout(10)
                ->retry(2, 100, function (\Throwable $exception, $request) {
                    return $exception instanceof ConnectionException;
                }, throw: false)
                ->post($url, $payload);
        } catch (\Throwable $e) {
            throw new RuntimeException('FIREBASE_REQUEST_FAILED', 0, $e);
        }

        if ($response->successful()) {
            return $response->json() ?? ['success' => true];
        }

        // Handle error response safely
        $this->handleFcmError($response, $plainFcmToken);

        throw new RuntimeException('FIREBASE_REQUEST_FAILED');
    }

    /**
     * Inspect FCM error response and deactivate the token ONLY if permanently unregistered.
     *
     * @param  Response  $response
     * @param  string  $plainFcmToken
     * @return void
     */
    protected function handleFcmError(Response $response, string $plainFcmToken): void
    {
        $status = $response->status();
        $json = $response->json();

        $tokenHash = hash('sha256', $plainFcmToken);
        $isPermanentlyUnregistered = false;

        if (is_array($json) && isset($json['error'])) {
            $error = $json['error'];
            $details = $error['details'] ?? [];

            if (is_array($details)) {
                foreach ($details as $detail) {
                    if (
                        isset($detail['@type'])
                        && $detail['@type'] === 'type.googleapis.com/google.firebase.fcm.v1.FcmError'
                        && isset($detail['errorCode'])
                        && $detail['errorCode'] === 'UNREGISTERED'
                    ) {
                        $isPermanentlyUnregistered = true;
                        break;
                    }
                }
            }
        }

        if ($isPermanentlyUnregistered) {
            $this->deviceService->deactivateTokenHash($tokenHash);
            throw new RuntimeException('FCM_TOKEN_INVALID');
        }

        Log::warning('FCM HTTP v1 request failed', [
            'status' => $status,
            'token_hash' => substr($tokenHash, 0, 12) . '...',
        ]);
    }
}
