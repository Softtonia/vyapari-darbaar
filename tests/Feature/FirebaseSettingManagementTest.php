<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\FirebaseSetting;
use App\Models\Role;
use App\Services\Firebase\Contracts\FirebaseAccessTokenProvider;
use App\Services\Firebase\Data\FirebaseAccessToken;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirebaseSettingManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->admin = Admin::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@vyaparidarbar.com',
            'password' => bcrypt('Password@123'),
            'status' => 'active',
        ]);

        $adminRole = Role::where('name', 'admin')->where('guard_name', 'admin')->first();
        $this->admin->assignRole($adminRole);

        $this->adminToken = $this->admin->createToken('admin-token', ['*'])->plainTextToken;

        // Bind a fake FirebaseAccessTokenProvider to prevent external network calls
        $this->app->instance(FirebaseAccessTokenProvider::class, new class implements FirebaseAccessTokenProvider {
            public function getAccessToken(array $serviceAccount, string $projectId): FirebaseAccessToken
            {
                return new FirebaseAccessToken('fake-oauth2-token', time() + 3600, 'test-fingerprint');
            }
        });
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Accept' => 'application/json',
        ];
    }

    protected function validServiceAccountJson(string $projectId = 'vyapari-darbaar-test'): string
    {
        return json_encode([
            'type' => 'service_account',
            'project_id' => $projectId,
            'private_key_id' => 'fake_key_id_123',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQCfake\n-----END PRIVATE KEY-----\n",
            'client_email' => 'firebase-adminsdk@vyapari-darbaar-test.iam.gserviceaccount.com',
            'client_id' => '1234567890',
            'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
            'token_uri' => 'https://oauth2.googleapis.com/token',
            'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
            'client_x509_cert_url' => 'https://www.googleapis.com/robot/v1/metadata/x509/firebase-adminsdk.iam.gserviceaccount.com',
        ]);
    }

    protected function validJsWebConfig(string $projectId = 'vyapari-darbaar-test'): string
    {
        return <<<JS
const firebaseConfig = {
  apiKey: "AIzaSyTestApiKey123",
  authDomain: "{$projectId}.firebaseapp.com",
  projectId: "{$projectId}",
  storageBucket: "{$projectId}.appspot.com",
  messagingSenderId: "123456789",
  appId: "1:123456789:web:abcdef"
};
JS;
    }

    public function test_unauthenticated_user_cannot_access_firebase_settings(): void
    {
        $response = $this->getJson('/api/admin/settings/firebase');
        $response->assertStatus(401);
    }

    public function test_admin_without_view_permission_is_forbidden(): void
    {
        $limitedAdmin = Admin::create([
            'first_name' => 'Limited',
            'last_name' => 'Admin',
            'email' => 'limited@vyaparidarbar.com',
            'password' => bcrypt('Password@123'),
            'status' => 'active',
        ]);
        $token = $limitedAdmin->createToken('limited-token', ['*'])->plainTextToken;

        $response = $this->getJson('/api/admin/settings/firebase', [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);
        $response->assertStatus(403);
        $response->assertJson([
            'status' => false,
            'message' => 'Unauthorized action. Missing firebase-setting.view permission.',
        ]);
    }

    public function test_get_settings_when_not_configured_returns_null_data(): void
    {
        $response = $this->getJson('/api/admin/settings/firebase', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'message' => 'Firebase settings not configured.',
            'data' => null,
        ]);
    }

    public function test_initial_setup_requires_web_config(): void
    {
        $payload = [
            'vapid_key' => 'BNTestVapidKey123',
            'service_account_json' => $this->validServiceAccountJson(),
            'status' => true,
        ];

        $response = $this->putJson('/api/admin/settings/firebase', $payload, $this->authHeaders());
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['web_config']);
        $response->assertJsonFragment([
            'web_config' => ['Paste your Firebase Web App configuration.'],
        ]);
    }

    public function test_unparseable_web_config_returns_user_friendly_error(): void
    {
        $payload = [
            'web_config' => 'console.log("no firebase config here")',
            'vapid_key' => 'BNTestVapidKey123',
            'service_account_json' => $this->validServiceAccountJson(),
            'status' => true,
        ];

        $response = $this->putJson('/api/admin/settings/firebase', $payload, $this->authHeaders());
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['web_config']);
        $response->assertJsonFragment([
            'web_config' => ["We couldn't read the Firebase configuration. Copy it again from Firebase Project Settings → Your Apps → SDK setup and configuration."],
        ]);
    }

    public function test_partial_web_config_missing_api_key_returns_friendly_error(): void
    {
        $payload = [
            'web_config' => [
                'authDomain' => 'vyapari-darbaar-test.firebaseapp.com',
                'projectId' => 'vyapari-darbaar-test',
                'messagingSenderId' => '123456789',
                'appId' => '1:123456789:web:abcdef',
            ],
            'vapid_key' => 'BNTestVapidKey123',
            'service_account_json' => $this->validServiceAccountJson(),
            'status' => true,
        ];

        $response = $this->putJson('/api/admin/settings/firebase', $payload, $this->authHeaders());
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['web_config']);
        $response->assertJsonFragment([
            'web_config' => ['Firebase API Key could not be detected in the pasted Web App configuration.'],
        ]);
    }

    public function test_initial_setup_requires_service_account_json(): void
    {
        $payload = [
            'web_config' => $this->validJsWebConfig(),
            'vapid_key' => 'BNTestVapidKey123',
            'status' => true,
        ];

        $response = $this->putJson('/api/admin/settings/firebase', $payload, $this->authHeaders());
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['service_account_json']);
    }

    public function test_invalid_service_account_json_is_rejected(): void
    {
        $payload = [
            'web_config' => $this->validJsWebConfig(),
            'vapid_key' => 'BNTestVapidKey123',
            'service_account_json' => 'not-a-valid-json',
            'status' => true,
        ];

        $response = $this->putJson('/api/admin/settings/firebase', $payload, $this->authHeaders());
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['service_account_json']);
        $response->assertJsonFragment([
            'service_account_json' => ['The Service Account JSON is invalid. Download a fresh JSON key from Firebase Project Settings → Service Accounts.'],
        ]);
    }

    public function test_service_account_project_id_mismatch_with_web_config_is_rejected(): void
    {
        $payload = [
            'web_config' => $this->validJsWebConfig('vyapari-darbaar-web'),
            'vapid_key' => 'BNTestVapidKey123',
            'service_account_json' => $this->validServiceAccountJson('different-service-account-project'),
            'status' => true,
        ];

        $response = $this->putJson('/api/admin/settings/firebase', $payload, $this->authHeaders());
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['service_account_json']);
        $response->assertJsonFragment([
            'service_account_json' => ['The Firebase Web App and Service Account belong to different Firebase projects.'],
        ]);
    }

    public function test_successful_setup_with_pasted_js_snippet_creates_singleton_and_encrypts_credentials(): void
    {
        $payload = [
            'web_config' => $this->validJsWebConfig('vyapari-darbaar-test'),
            'vapid_key' => 'BNTestVapidKey123',
            'service_account_json' => $this->validServiceAccountJson('vyapari-darbaar-test'),
            'status' => true,
        ];

        $response = $this->putJson('/api/admin/settings/firebase', $payload, $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'message' => 'Firebase settings updated successfully.',
            'data' => [
                'web_config' => [
                    'apiKey' => 'AIzaSyTestApiKey123',
                    'authDomain' => 'vyapari-darbaar-test.firebaseapp.com',
                    'projectId' => 'vyapari-darbaar-test',
                    'storageBucket' => 'vyapari-darbaar-test.appspot.com',
                    'messagingSenderId' => '123456789',
                    'appId' => '1:123456789:web:abcdef',
                ],
                'vapid_key' => 'BNTestVapidKey123',
                'service_account_configured' => true,
                'status' => true,
            ],
        ]);

        // Verify service account JSON is never exposed in the response
        $response->assertJsonMissing(['service_account_json']);
        $response->assertJsonMissing(['private_key']);
        $response->assertJsonMissing(['client_email']);

        // Verify database state: single row with ID = 1 and encrypted service account
        $this->assertDatabaseCount('firebase_settings', 1);
        $rawRecord = DB::table('firebase_settings')->where('id', 1)->first();
        $this->assertNotNull($rawRecord);
        $this->assertEquals('AIzaSyTestApiKey123', $rawRecord->api_key);
        $this->assertEquals('vyapari-darbaar-test', $rawRecord->project_id);
        $this->assertNotEquals($this->validServiceAccountJson('vyapari-darbaar-test'), $rawRecord->service_account_json);
        $this->assertStringNotContainsString('-----BEGIN PRIVATE KEY-----', $rawRecord->service_account_json);

        // Model decrypts properly
        $model = FirebaseSetting::find(1);
        $this->assertStringContainsString('-----BEGIN PRIVATE KEY-----', $model->service_account_json);
    }

    public function test_get_settings_returns_clean_structured_web_config_for_admin(): void
    {
        $this->putJson('/api/admin/settings/firebase', [
            'web_config' => $this->validJsWebConfig('vyapari-darbaar-test'),
            'vapid_key' => 'BNTestVapidKey123',
            'service_account_json' => $this->validServiceAccountJson('vyapari-darbaar-test'),
            'status' => true,
        ], $this->authHeaders());

        $response = $this->getJson('/api/admin/settings/firebase', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'message' => 'Firebase settings retrieved successfully.',
            'data' => [
                'web_config' => [
                    'apiKey' => 'AIzaSyTestApiKey123',
                    'authDomain' => 'vyapari-darbaar-test.firebaseapp.com',
                    'projectId' => 'vyapari-darbaar-test',
                    'storageBucket' => 'vyapari-darbaar-test.appspot.com',
                    'messagingSenderId' => '123456789',
                    'appId' => '1:123456789:web:abcdef',
                ],
                'vapid_key' => 'BNTestVapidKey123',
                'service_account_configured' => true,
                'status' => true,
            ],
        ]);

        $response->assertJsonMissing(['service_account_json']);
        $response->assertJsonMissing(['private_key']);
    }

    public function test_backward_compatibility_supports_individual_fields(): void
    {
        $payload = [
            'api_key' => 'AIzaSyLegacyKey',
            'auth_domain' => 'legacy.firebaseapp.com',
            'project_id' => 'legacy-project',
            'storage_bucket' => 'legacy.appspot.com',
            'messaging_sender_id' => '55667788',
            'app_id' => '1:55667788:web:legacyapp',
            'vapid_key' => 'BNLegacyVapidKey',
            'service_account_json' => $this->validServiceAccountJson('legacy-project'),
            'status' => true,
        ];

        $response = $this->putJson('/api/admin/settings/firebase', $payload, $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'data' => [
                'web_config' => [
                    'apiKey' => 'AIzaSyLegacyKey',
                    'projectId' => 'legacy-project',
                ],
                'vapid_key' => 'BNLegacyVapidKey',
                'service_account_configured' => true,
                'status' => true,
            ],
        ]);
    }

    public function test_update_omitting_service_account_retains_existing_credentials(): void
    {
        // First setup
        $payload1 = [
            'web_config' => $this->validJsWebConfig('vyapari-darbaar-test'),
            'vapid_key' => 'BNTestVapidKey123',
            'service_account_json' => $this->validServiceAccountJson('vyapari-darbaar-test'),
            'status' => true,
        ];
        $this->putJson('/api/admin/settings/firebase', $payload1, $this->authHeaders());

        $originalModel = FirebaseSetting::find(1);
        $originalServiceAccount = $originalModel->service_account_json;

        // Second setup omitting service_account_json and changing api_key in web_config
        $updatedJs = <<<JS
const firebaseConfig = {
  apiKey: "AIzaSyUpdatedApiKey456",
  authDomain: "vyapari-darbaar-test.firebaseapp.com",
  projectId: "vyapari-darbaar-test",
  storageBucket: "vyapari-darbaar-test.appspot.com",
  messagingSenderId: "123456789",
  appId: "1:123456789:web:abcdef"
};
JS;

        $payload2 = [
            'web_config' => $updatedJs,
            'vapid_key' => 'BNTestVapidKey123',
            'service_account_json' => '********',
            'status' => true,
        ];
        $response = $this->putJson('/api/admin/settings/firebase', $payload2, $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'data' => [
                'web_config' => [
                    'apiKey' => 'AIzaSyUpdatedApiKey456',
                ],
                'service_account_configured' => true,
            ],
        ]);

        $updatedModel = FirebaseSetting::find(1);
        $this->assertEquals($originalServiceAccount, $updatedModel->service_account_json);
    }

    public function test_update_with_new_service_account_replaces_credentials(): void
    {
        // Setup initial
        $this->putJson('/api/admin/settings/firebase', [
            'web_config' => $this->validJsWebConfig('vyapari-darbaar-test'),
            'vapid_key' => 'BNTestVapidKey123',
            'service_account_json' => $this->validServiceAccountJson('vyapari-darbaar-test'),
            'status' => true,
        ], $this->authHeaders());

        $newServiceAccount = json_encode([
            'type' => 'service_account',
            'project_id' => 'vyapari-darbaar-test',
            'private_key_id' => 'new_key_id_999',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQCnewkey\n-----END PRIVATE KEY-----\n",
            'client_email' => 'new-service-account@vyapari-darbaar-test.iam.gserviceaccount.com',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]);

        $response = $this->putJson('/api/admin/settings/firebase', [
            'web_config' => $this->validJsWebConfig('vyapari-darbaar-test'),
            'vapid_key' => 'BNTestVapidKey123',
            'service_account_json' => $newServiceAccount,
            'status' => true,
        ], $this->authHeaders());

        $response->assertStatus(200);

        $updatedModel = FirebaseSetting::find(1);
        $this->assertStringContainsString('new_key_id_999', $updatedModel->service_account_json);
    }

    public function test_admin_test_notification_endpoint_missing_configuration(): void
    {
        $response = $this->postJson('/api/admin/settings/firebase/test', [
            'fcm_token' => 'fake_test_device_token',
        ], $this->authHeaders());

        $response->assertStatus(422);
        $response->assertJson([
            'status' => false,
            'message' => 'Firebase configuration is missing.',
            'error' => 'FIREBASE_CONFIGURATION_MISSING',
        ]);
    }

    public function test_admin_test_notification_works_even_when_status_is_disabled(): void
    {
        // Setup configuration with status = false
        $this->putJson('/api/admin/settings/firebase', [
            'web_config' => $this->validJsWebConfig('vyapari-darbaar-test'),
            'vapid_key' => 'BNTestVapidKey123',
            'service_account_json' => $this->validServiceAccountJson('vyapari-darbaar-test'),
            'status' => false,
        ], $this->authHeaders());

        Http::fake([
            'https://fcm.googleapis.com/v1/projects/vyapari-darbaar-test/messages:send' => Http::response([
                'name' => 'projects/vyapari-darbaar-test/messages/mock_message_id_123',
            ], 200),
        ]);

        $response = $this->postJson('/api/admin/settings/firebase/test', [
            'fcm_token' => 'fake_test_device_token',
            'title' => 'Test Notification',
            'body' => 'Test Body',
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'message' => 'Firebase test notification sent successfully.',
        ]);
    }

    public function test_admin_test_notification_handles_fcm_failure(): void
    {
        $this->putJson('/api/admin/settings/firebase', [
            'web_config' => $this->validJsWebConfig('vyapari-darbaar-test'),
            'vapid_key' => 'BNTestVapidKey123',
            'service_account_json' => $this->validServiceAccountJson('vyapari-darbaar-test'),
            'status' => true,
        ], $this->authHeaders());

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

        $response = $this->postJson('/api/admin/settings/firebase/test', [
            'fcm_token' => 'unregistered_device_token',
        ], $this->authHeaders());

        $response->assertStatus(422);
        $response->assertJson([
            'status' => false,
            'message' => 'Failed to send Firebase test notification.',
            'error' => 'FCM_TOKEN_INVALID',
        ]);
    }

    public function test_admin_test_notification_is_rate_limited(): void
    {
        $this->putJson('/api/admin/settings/firebase', [
            'web_config' => $this->validJsWebConfig('vyapari-darbaar-test'),
            'vapid_key' => 'BNTestVapidKey123',
            'service_account_json' => $this->validServiceAccountJson('vyapari-darbaar-test'),
            'status' => true,
        ], $this->authHeaders());

        Http::fake([
            'https://fcm.googleapis.com/v1/projects/vyapari-darbaar-test/messages:send' => Http::response([
                'name' => 'projects/vyapari-darbaar-test/messages/mock_message_id_123',
            ], 200),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/admin/settings/firebase/test', [
                'fcm_token' => 'fake_test_device_token',
            ], $this->authHeaders());
            $response->assertStatus(200);
        }

        // 6th attempt should trigger 429
        $response = $this->postJson('/api/admin/settings/firebase/test', [
            'fcm_token' => 'fake_test_device_token',
        ], $this->authHeaders());
        $response->assertStatus(429);
    }
}
