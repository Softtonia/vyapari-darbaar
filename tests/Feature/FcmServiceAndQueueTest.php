<?php

namespace Tests\Feature;

use App\Jobs\SendFcmNotificationJob;
use App\Models\FirebaseSetting;
use App\Models\NotificationDevice;
use App\Models\User;
use App\Services\Firebase\Contracts\FirebaseAccessTokenProvider;
use App\Services\Firebase\Data\FirebaseAccessToken;
use App\Services\Firebase\FcmService;
use App\Services\Firebase\FirebaseConfigService;
use App\Services\Firebase\GoogleFirebaseAccessTokenProvider;
use App\Services\Firebase\NotificationDeviceService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FcmServiceAndQueueTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $plainToken;
    protected string $tokenHash;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->seed(RoleSeeder::class);

        $this->user = User::create([
            'first_name' => 'Alice',
            'last_name' => 'Tester',
            'username' => 'alicetester',
            'email' => 'alice@example.com',
            'password' => bcrypt('Password@123'),
            'status' => 'active',
        ]);

        $this->plainToken = 'alice_fcm_token_12345_sample';
        $this->tokenHash = hash('sha256', $this->plainToken);

        NotificationDevice::create([
            'user_id' => $this->user->id,
            'fcm_token' => $this->plainToken,
            'fcm_token_hash' => $this->tokenHash,
            'device_type' => 'web',
            'is_active' => true,
        ]);

        // Mock OAuth provider
        $this->app->instance(FirebaseAccessTokenProvider::class, new class implements FirebaseAccessTokenProvider {
            public function getAccessToken(array $serviceAccount, string $projectId): FirebaseAccessToken
            {
                return new FirebaseAccessToken('mocked-bearer-token', time() + 3600, 'mock-fingerprint');
            }
        });
    }

    protected function createFirebaseSetting(bool $status = true): FirebaseSetting
    {
        return FirebaseSetting::create([
            'id' => 1,
            'api_key' => 'AIzaSyTestApiKey123',
            'auth_domain' => 'vyapari-darbaar-test.firebaseapp.com',
            'project_id' => 'vyapari-darbaar-test',
            'messaging_sender_id' => '123456789',
            'app_id' => '1:123456789:web:abcdef',
            'vapid_key' => 'BNTestVapidKey123',
            'service_account_json' => json_encode([
                'type' => 'service_account',
                'project_id' => 'vyapari-darbaar-test',
                'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQCfake\n-----END PRIVATE KEY-----\n",
                'client_email' => 'firebase-adminsdk@vyapari-darbaar-test.iam.gserviceaccount.com',
                'token_uri' => 'https://oauth2.googleapis.com/token',
            ]),
            'status' => $status,
        ]);
    }

    public function test_send_fcm_notification_job_is_queued_properly(): void
    {
        Queue::fake();

        SendFcmNotificationJob::dispatch(
            $this->user->id,
            'Order Update',
            'Your order has been confirmed.',
            ['order_id' => '1001']
        );

        Queue::assertPushed(SendFcmNotificationJob::class, function (SendFcmNotificationJob $job) {
            $this->assertEquals($this->user->id, $job->userId);
            $this->assertEquals('Order Update', $job->title);
            $this->assertEquals('Your order has been confirmed.', $job->body);
            $this->assertEquals(['order_id' => '1001'], $job->data);
            $this->assertEquals('notifications', $job->queue);

            return true;
        });
    }

    public function test_send_fcm_notification_job_does_not_serialize_credentials(): void
    {
        $job = new SendFcmNotificationJob(
            $this->user->id,
            'Security Test',
            'Checking serialized payload'
        );

        $serialized = serialize($job);

        $this->assertStringNotContainsString('service_account', $serialized);
        $this->assertStringNotContainsString('private_key', $serialized);
        $this->assertStringNotContainsString('mocked-bearer-token', $serialized);
        $this->assertStringNotContainsString('AIzaSyTestApiKey', $serialized);
    }

    public function test_job_skips_execution_when_firebase_is_disabled(): void
    {
        $this->createFirebaseSetting(status: false);

        Http::fake();

        $job = new SendFcmNotificationJob(
            $this->user->id,
            'Title',
            'Body'
        );

        $job->handle(
            app(FcmService::class),
            app(FirebaseConfigService::class)
        );

        Http::assertNothingSent();
    }

    public function test_job_sends_notification_to_user_devices_when_enabled(): void
    {
        $this->createFirebaseSetting(status: true);

        Http::fake([
            'https://fcm.googleapis.com/v1/projects/vyapari-darbaar-test/messages:send' => Http::response([
                'name' => 'projects/vyapari-darbaar-test/messages/msg_123',
            ], 200),
        ]);

        $job = new SendFcmNotificationJob(
            $this->user->id,
            'Hello Alice',
            'Welcome to Vyapari Darbaar'
        );

        $job->handle(
            app(FcmService::class),
            app(FirebaseConfigService::class)
        );

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://fcm.googleapis.com/v1/projects/vyapari-darbaar-test/messages:send'
                && $data['message']['token'] === $this->plainToken
                && $data['message']['notification']['title'] === 'Hello Alice'
                && $data['message']['notification']['body'] === 'Welcome to Vyapari Darbaar';
        });
    }

    public function test_fcm_unregistered_error_deactivates_matching_device_token(): void
    {
        $this->createFirebaseSetting(status: true);

        Http::fake([
            'https://fcm.googleapis.com/v1/projects/vyapari-darbaar-test/messages:send' => Http::response([
                'error' => [
                    'code' => 404,
                    'message' => 'Requested entity was not found.',
                    'status' => 'NOT_FOUND',
                    'details' => [
                        [
                            '@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError',
                            'errorCode' => 'UNREGISTERED',
                        ],
                    ],
                ],
            ], 404),
        ]);

        $fcmService = app(FcmService::class);

        try {
            $fcmService->sendToDevice($this->plainToken, 'Title', 'Body');
        } catch (\Throwable) {
            // Expected
        }

        $device = NotificationDevice::where('fcm_token_hash', $this->tokenHash)->first();
        $this->assertFalse($device->is_active);
    }

    public function test_generic_fcm_error_does_not_deactivate_device_token(): void
    {
        $this->createFirebaseSetting(status: true);

        // Generic INVALID_ARGUMENT (e.g. bad payload format or network issue)
        Http::fake([
            'https://fcm.googleapis.com/v1/projects/vyapari-darbaar-test/messages:send' => Http::response([
                'error' => [
                    'code' => 400,
                    'message' => 'Invalid argument payload',
                    'status' => 'INVALID_ARGUMENT',
                ],
            ], 400),
        ]);

        $fcmService = app(FcmService::class);

        try {
            $fcmService->sendToDevice($this->plainToken, 'Title', 'Body');
        } catch (\Throwable) {
            // Expected
        }

        // Token must REMAIN active
        $device = NotificationDevice::where('fcm_token_hash', $this->tokenHash)->first();
        $this->assertTrue($device->is_active);
    }

    public function test_generic_not_found_unauthenticated_and_server_errors_do_not_deactivate_token(): void
    {
        $this->createFirebaseSetting(status: true);

        $fcmService = app(FcmService::class);

        $nonDeactivatingResponses = [
            // Generic NOT_FOUND without UNREGISTERED detail
            ['code' => 404, 'status' => 'NOT_FOUND', 'message' => 'Entity not found'],
            // UNAUTHENTICATED
            ['code' => 401, 'status' => 'UNAUTHENTICATED', 'message' => 'Invalid auth credentials'],
            // PERMISSION_DENIED
            ['code' => 403, 'status' => 'PERMISSION_DENIED', 'message' => 'Caller does not have permission'],
            // Server 500
            ['code' => 500, 'status' => 'INTERNAL', 'message' => 'Internal server error'],
        ];

        foreach ($nonDeactivatingResponses as $err) {
            Http::fake([
                'https://fcm.googleapis.com/v1/projects/vyapari-darbaar-test/messages:send' => Http::response([
                    'error' => [
                        'code' => $err['code'],
                        'status' => $err['status'],
                        'message' => $err['message'],
                    ],
                ], $err['code']),
            ]);

            try {
                $fcmService->sendToDevice($this->plainToken, 'Title', 'Body');
            } catch (\Throwable) {
                // Expected
            }

            // Must remain active
            $device = NotificationDevice::where('fcm_token_hash', $this->tokenHash)->first();
            $this->assertTrue($device->is_active, "Token should remain active for {$err['status']}");
        }
    }

    public function test_credential_fingerprint_and_token_expiry_behavior(): void
    {
        $provider = new GoogleFirebaseAccessTokenProvider;

        $sa1 = [
            'client_email' => 'admin1@test.iam.gserviceaccount.com',
            'private_key_id' => 'key1',
        ];
        $sa2 = [
            'client_email' => 'admin2@test.iam.gserviceaccount.com',
            'private_key_id' => 'key2',
        ];

        $fp1 = $provider->calculateFingerprint($sa1, 'proj1');
        $fp2 = $provider->calculateFingerprint($sa2, 'proj1');
        $fp3 = $provider->calculateFingerprint($sa1, 'proj2');

        $this->assertNotEquals($fp1, $fp2);
        $this->assertNotEquals($fp1, $fp3);

        $validToken = new FirebaseAccessToken('valid_tok', time() + 3600, $fp1);
        $this->assertFalse($validToken->isExpired(60));

        $expiringToken = new FirebaseAccessToken('expiring_tok', time() + 30, $fp1);
        $this->assertTrue($expiringToken->isExpired(60));
    }

    public function test_container_resolves_firebase_access_token_provider_as_singleton_and_injects_into_fcm_service(): void
    {
        // Forget test instance override to test default container binding
        $this->app->forgetInstance(FirebaseAccessTokenProvider::class);

        $provider1 = $this->app->make(FirebaseAccessTokenProvider::class);
        $provider2 = $this->app->make(FirebaseAccessTokenProvider::class);

        $this->assertInstanceOf(GoogleFirebaseAccessTokenProvider::class, $provider1);
        $this->assertSame($provider1, $provider2, 'FirebaseAccessTokenProvider must resolve as a singleton instance');

        $fcmService = $this->app->make(FcmService::class);
        $this->assertInstanceOf(FcmService::class, $fcmService);
    }
}
