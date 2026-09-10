<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\FirebaseSetting;
use App\Models\Role;
use App\Services\Firebase\FirebaseConfigService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublicFirebaseConfigTest extends TestCase
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
    }

    protected function validServiceAccountJson(): string
    {
        return json_encode([
            'type' => 'service_account',
            'project_id' => 'vyapari-darbaar-test',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQCfake\n-----END PRIVATE KEY-----\n",
            'client_email' => 'firebase-adminsdk@vyapari-darbaar-test.iam.gserviceaccount.com',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]);
    }

    public function test_returns_503_when_no_firebase_settings_exist(): void
    {
        $response = $this->getJson('/api/firebase/config');
        $response->assertStatus(503);
        $response->assertJson([
            'status' => false,
            'message' => 'Firebase notifications are currently unavailable.',
            'error' => 'FIREBASE_NOT_AVAILABLE',
        ]);
    }

    public function test_returns_503_when_firebase_settings_exist_but_disabled(): void
    {
        FirebaseSetting::create([
            'id' => 1,
            'api_key' => 'AIzaSyTestApiKey123',
            'auth_domain' => 'vyapari-darbaar-test.firebaseapp.com',
            'project_id' => 'vyapari-darbaar-test',
            'messaging_sender_id' => '123456789',
            'app_id' => '1:123456789:web:abcdef',
            'vapid_key' => 'BNTestVapidKey123',
            'service_account_json' => $this->validServiceAccountJson(),
            'status' => false,
        ]);

        $response = $this->getJson('/api/firebase/config');
        $response->assertStatus(503);
        $response->assertJson([
            'status' => false,
            'message' => 'Firebase notifications are currently unavailable.',
            'error' => 'FIREBASE_NOT_AVAILABLE',
        ]);
    }

    public function test_returns_only_safe_public_fields_when_enabled(): void
    {
        FirebaseSetting::create([
            'id' => 1,
            'api_key' => 'AIzaSyTestApiKey123',
            'auth_domain' => 'vyapari-darbaar-test.firebaseapp.com',
            'project_id' => 'vyapari-darbaar-test',
            'storage_bucket' => 'vyapari-darbaar-test.appspot.com',
            'messaging_sender_id' => '123456789',
            'app_id' => '1:123456789:web:abcdef',
            'vapid_key' => 'BNTestVapidKey123',
            'service_account_json' => $this->validServiceAccountJson(),
            'status' => true,
        ]);

        $response = $this->getJson('/api/firebase/config');
        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'message' => 'Firebase configuration retrieved successfully.',
            'data' => [
                'api_key' => 'AIzaSyTestApiKey123',
                'auth_domain' => 'vyapari-darbaar-test.firebaseapp.com',
                'project_id' => 'vyapari-darbaar-test',
                'storage_bucket' => 'vyapari-darbaar-test.appspot.com',
                'messaging_sender_id' => '123456789',
                'app_id' => '1:123456789:web:abcdef',
                'vapid_key' => 'BNTestVapidKey123',
            ],
        ]);

        // Assert sensitive secrets are never included
        $response->assertJsonMissing(['service_account_json']);
        $response->assertJsonMissing(['private_key']);
        $response->assertJsonMissing(['client_email']);
        $response->assertJsonMissing(['token_uri']);
        $response->assertJsonMissing(['service_account_configured']);
    }

    public function test_public_config_caching_and_cache_invalidation(): void
    {
        $service = app(FirebaseConfigService::class);

        FirebaseSetting::create([
            'id' => 1,
            'api_key' => 'OriginalApiKey',
            'auth_domain' => 'vyapari-darbaar-test.firebaseapp.com',
            'project_id' => 'vyapari-darbaar-test',
            'messaging_sender_id' => '123456789',
            'app_id' => '1:123456789:web:abcdef',
            'vapid_key' => 'BNTestVapidKey123',
            'service_account_json' => $this->validServiceAccountJson(),
            'status' => true,
        ]);

        // First call populates cache
        $this->assertFalse(Cache::has(FirebaseConfigService::PUBLIC_CACHE_KEY));
        $res1 = $this->getJson('/api/firebase/config');
        $res1->assertStatus(200);
        $this->assertTrue(Cache::has(FirebaseConfigService::PUBLIC_CACHE_KEY));

        // Update settings via admin API
        $this->putJson('/api/admin/settings/firebase', [
            'api_key' => 'UpdatedApiKey',
            'auth_domain' => 'vyapari-darbaar-test.firebaseapp.com',
            'project_id' => 'vyapari-darbaar-test',
            'messaging_sender_id' => '123456789',
            'app_id' => '1:123456789:web:abcdef',
            'vapid_key' => 'BNTestVapidKey123',
            'service_account_json' => '********',
            'status' => true,
        ], [
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Accept' => 'application/json',
        ]);

        // Verify cache was invalidated
        $this->assertFalse(Cache::has(FirebaseConfigService::PUBLIC_CACHE_KEY));

        // Next request gets the updated key
        $res2 = $this->getJson('/api/firebase/config');
        $res2->assertStatus(200);
        $res2->assertJsonPath('data.api_key', 'UpdatedApiKey');
    }
}
