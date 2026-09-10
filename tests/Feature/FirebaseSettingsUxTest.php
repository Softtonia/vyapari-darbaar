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
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirebaseSettingsUxTest extends TestCase
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
        if ($adminRole) {
            $this->admin->assignRole($adminRole);
        }

        $this->adminToken = $this->admin->createToken('admin-token', ['*'])->plainTextToken;

        $this->app->instance(FirebaseAccessTokenProvider::class, new class implements FirebaseAccessTokenProvider {
            public function getAccessToken(array $serviceAccount, string $projectId): FirebaseAccessToken
            {
                return new FirebaseAccessToken('fake-oauth-token', time() + 3600, 'test-fingerprint');
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

    public function test_admin_can_save_settings_using_web_config_snippet(): void
    {
        $snippet = <<<JS
const firebaseConfig = {
  apiKey: "AIzaSyD-SnippetKey",
  authDomain: "snippet-project.firebaseapp.com",
  projectId: "snippet-project",
  storageBucket: "snippet-project.appspot.com",
  messagingSenderId: "987654321098",
  appId: "1:987654321098:web:snippet"
};
JS;

        $serviceAccount = json_encode([
            'type' => 'service_account',
            'project_id' => 'snippet-project',
            'private_key' => 'fake_private_key',
            'client_email' => 'service@snippet-project.iam.gserviceaccount.com',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]);

        $response = $this->postJson('/api/admin/settings/firebase', [
            'web_config' => $snippet,
            'vapid_key' => 'BEl-snippet-vapid-key',
            'service_account_json' => $serviceAccount,
            'status' => true,
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.web_config.projectId', 'snippet-project')
            ->assertJsonPath('data.service_account_configured', true);

        $this->assertDatabaseHas('firebase_settings', [
            'project_id' => 'snippet-project',
            'app_id' => '1:987654321098:web:snippet',
            'status' => true,
        ]);
    }

    public function test_admin_can_configure_firebase_via_multipart_file_upload(): void
    {
        $snippet = <<<JS
const firebaseConfig = {
  apiKey: "AIzaSyD-MultipartKey",
  projectId: "multipart-project",
  messagingSenderId: "123456789012",
  appId: "1:123456789012:web:multipart"
};
JS;

        $serviceAccountJson = json_encode([
            'type' => 'service_account',
            'project_id' => 'multipart-project',
            'private_key' => 'fake_private_key',
            'client_email' => 'service@multipart-project.iam.gserviceaccount.com',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('service_account.json', $serviceAccountJson);

        $response = $this->post('/api/admin/settings/firebase', [
            'web_config' => $snippet,
            'vapid_key' => 'vapid_multipart_key_123',
            'service_account' => $file,
            'status' => '1',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.web_config.projectId', 'multipart-project')
            ->assertJsonPath('data.web_config.apiKey', 'AIzaSyD-MultipartKey')
            ->assertJsonPath('data.service_account_configured', true);

        $this->assertDatabaseHas('firebase_settings', [
            'project_id' => 'multipart-project',
            'status' => true,
        ]);
    }

    public function test_validation_surfaces_user_friendly_messages(): void
    {
        // 1. Missing web config and service account on first setup
        $response = $this->postJson('/api/admin/settings/firebase', [
            'vapid_key' => 'test-vapid',
            'status' => 0,
        ], $this->authHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['web_config', 'service_account'])
            ->assertJsonPath('errors.web_config.0', 'Paste the Firebase Web App configuration.')
            ->assertJsonPath('errors.service_account.0', 'Upload the Service Account JSON downloaded from Firebase.');

        // 2. Invalid web config
        $response2 = $this->postJson('/api/admin/settings/firebase', [
            'web_config' => 'this is not valid javascript or json',
            'vapid_key' => 'test-vapid',
            'status' => 0,
        ], $this->authHeaders());

        $response2->assertStatus(422)
            ->assertJsonValidationErrors(['web_config'])
            ->assertJsonPath('errors.web_config.0', 'We could not read the Firebase Web App configuration.');

        // 3. Project mismatch
        $snippet = 'const firebaseConfig = { apiKey: "k", projectId: "proj-A", messagingSenderId: "1", appId: "a" };';
        $mismatchSa = json_encode([
            'type' => 'service_account',
            'project_id' => 'proj-B',
            'private_key' => 'pk',
            'client_email' => 'e@b.com',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]);

        $response3 = $this->postJson('/api/admin/settings/firebase', [
            'web_config' => $snippet,
            'service_account_json' => $mismatchSa,
            'vapid_key' => 'test-vapid',
            'status' => 0,
        ], $this->authHeaders());

        $response3->assertStatus(422)
            ->assertJsonValidationErrors(['service_account'])
            ->assertJsonPath('errors.service_account.0', 'The Firebase Web App and Service Account belong to different projects.');
    }

    public function test_updating_firebase_settings_retains_existing_service_account_when_no_file_uploaded(): void
    {
        FirebaseSetting::create([
            'id' => 1,
            'api_key' => 'old_key',
            'auth_domain' => 'my-proj.firebaseapp.com',
            'project_id' => 'my-proj',
            'messaging_sender_id' => '123',
            'app_id' => 'app123',
            'vapid_key' => 'old_vapid',
            'service_account_json' => json_encode([
                'type' => 'service_account',
                'project_id' => 'my-proj',
                'private_key' => 'secret_private_key',
                'client_email' => 'client@my-proj.iam.gserviceaccount.com',
                'token_uri' => 'https://oauth2.googleapis.com/token',
            ]),
            'status' => false,
        ]);

        $newSnippet = 'const firebaseConfig = { apiKey: "new_api_key_456", projectId: "my-proj", messagingSenderId: "123", appId: "app123" };';

        // Update without uploading service_account file
        $response = $this->post('/api/admin/settings/firebase', [
            'web_config' => $newSnippet,
            'vapid_key' => 'new_vapid_456',
            'status' => '1',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.web_config.apiKey', 'new_api_key_456')
            ->assertJsonPath('data.vapid_key', 'new_vapid_456')
            ->assertJsonPath('data.service_account_configured', true);

        $setting = FirebaseSetting::find(1);
        $this->assertEquals('new_api_key_456', $setting->api_key);
        $this->assertStringContainsString('secret_private_key', $setting->service_account_json);
    }

    public function test_admin_can_send_test_notification_when_status_disabled(): void
    {
        Http::fake([
            'https://fcm.googleapis.com/v1/projects/test-proj/messages:send' => Http::response(['name' => 'projects/test-proj/messages/test_123'], 200),
        ]);

        FirebaseSetting::create([
            'id' => 1,
            'api_key' => 'key',
            'auth_domain' => 'test-proj.firebaseapp.com',
            'project_id' => 'test-proj',
            'messaging_sender_id' => '123',
            'app_id' => 'app123',
            'vapid_key' => 'vapid123',
            'service_account_json' => json_encode([
                'type' => 'service_account',
                'project_id' => 'test-proj',
                'private_key' => 'fake_key',
                'client_email' => 'client@test-proj.iam.gserviceaccount.com',
                'token_uri' => 'https://oauth2.googleapis.com/token',
            ]),
            'status' => false, // Disabled during test
        ]);

        $response = $this->postJson('/api/admin/settings/firebase/test', [
            'fcm_token' => 'fcm_test_token_12345',
            'title' => 'Test title',
            'body' => 'Test body',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Firebase test notification sent successfully.');
    }
}
