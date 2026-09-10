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

        $response = $this->putJson('/api/admin/settings/firebase', [
            'web_config' => $snippet,
            'vapid_key' => 'BEl-snippet-vapid-key',
            'service_account_json' => $serviceAccount,
            'status' => true,
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.project_id', 'snippet-project')
            ->assertJsonPath('data.service_account_configured', true);

        $this->assertDatabaseHas('firebase_settings', [
            'project_id' => 'snippet-project',
            'app_id' => '1:987654321098:web:snippet',
            'status' => true,
        ]);
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
