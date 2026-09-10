<?php

namespace Tests\Feature;

use App\Models\NotificationDevice;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationDeviceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user1;
    protected User $user2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->user1 = User::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe',
            'email' => 'john@example.com',
            'password' => bcrypt('Password@123'),
            'status' => 'active',
        ]);
        $this->user1->assignRole('user');

        $this->user2 = User::create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'username' => 'janesmith',
            'email' => 'jane@example.com',
            'password' => bcrypt('Password@123'),
            'status' => 'active',
        ]);
        $this->user2->assignRole('user');
    }

    public function test_unauthenticated_user_cannot_manage_devices(): void
    {
        $resStore = $this->postJson('/api/notifications/devices', [
            'fcm_token' => 'sample_token_123',
            'device_type' => 'web',
        ]);
        $resStore->assertStatus(401);

        $resDelete = $this->deleteJson('/api/notifications/devices', [
            'fcm_token' => 'sample_token_123',
        ]);
        $resDelete->assertStatus(401);
    }

    public function test_register_device_computes_hash_and_encrypts_token(): void
    {
        Sanctum::actingAs($this->user1, ['*']);

        $plainToken = 'fcm_token_sample_string_1234567890_abcdef';
        $expectedHash = hash('sha256', $plainToken);

        $response = $this->postJson('/api/notifications/devices', [
            'fcm_token' => $plainToken,
            'device_type' => 'web',
            'device_name' => 'Chrome on Windows',
            'browser' => 'Chrome',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'message' => 'Notification device registered successfully.',
            'data' => [
                'device_type' => 'web',
                'device_name' => 'Chrome on Windows',
                'browser' => 'Chrome',
                'is_active' => true,
            ],
        ]);

        // Assert token and token hash are NEVER exposed in Resource output
        $response->assertJsonMissing(['fcm_token']);
        $response->assertJsonMissing(['fcm_token_hash']);

        // Check database storage
        $this->assertDatabaseCount('notification_devices', 1);
        $rawRow = DB::table('notification_devices')->where('user_id', $this->user1->id)->first();
        $this->assertNotNull($rawRow);
        $this->assertEquals($expectedHash, $rawRow->fcm_token_hash);
        // Raw DB column must NOT store plain token (must be encrypted)
        $this->assertNotEquals($plainToken, $rawRow->fcm_token);

        // Eloquent model decrypts properly
        $deviceModel = NotificationDevice::find($rawRow->id);
        $this->assertEquals($plainToken, $deviceModel->fcm_token);
    }

    public function test_raw_fcm_token_is_encrypted_in_database_and_never_contains_plaintext(): void
    {
        $plainToken = 'very_secret_fcm_registration_token_xyz987654321';
        $expectedHash = hash('sha256', $plainToken);

        $device = NotificationDevice::create([
            'user_id' => $this->user1->id,
            'fcm_token' => $plainToken,
            'fcm_token_hash' => $expectedHash,
            'device_type' => 'android',
        ]);

        // Query raw DB value directly via DB facade
        $raw = DB::table('notification_devices')->where('id', $device->id)->first();

        $this->assertNotNull($raw);
        $this->assertNotEquals($plainToken, $raw->fcm_token);
        $this->assertStringNotContainsString('very_secret_fcm_registration_token', $raw->fcm_token);
        $this->assertEquals($expectedHash, $raw->fcm_token_hash);

        // Re-read via Eloquent and confirm automated decryption
        $freshDevice = NotificationDevice::find($device->id);
        $this->assertEquals($plainToken, $freshDevice->fcm_token);
    }

    public function test_register_duplicate_token_for_same_user_upserts_without_duplicate_rows(): void
    {
        Sanctum::actingAs($this->user1, ['*']);
        $plainToken = 'fcm_token_sample_string_123';

        // 1st Registration
        $this->postJson('/api/notifications/devices', [
            'fcm_token' => $plainToken,
            'device_type' => 'web',
            'device_name' => 'Initial Name',
            'browser' => 'Chrome',
        ]);

        $this->assertDatabaseCount('notification_devices', 1);

        // 2nd Registration with updated name
        $response = $this->postJson('/api/notifications/devices', [
            'fcm_token' => $plainToken,
            'device_type' => 'web',
            'device_name' => 'Updated Name',
            'browser' => 'Edge',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseCount('notification_devices', 1);

        $device = NotificationDevice::first();
        $this->assertEquals('Updated Name', $device->device_name);
        $this->assertEquals('Edge', $device->browser);
        $this->assertTrue($device->is_active);
    }

    public function test_token_reassignment_on_shared_browser_updates_user_ownership(): void
    {
        $plainToken = 'shared_browser_token_abc123';

        // User 1 logs in and registers token
        Sanctum::actingAs($this->user1, ['*']);
        $this->postJson('/api/notifications/devices', [
            'fcm_token' => $plainToken,
            'device_type' => 'web',
            'device_name' => 'User1 PC',
            'browser' => 'Chrome',
        ]);

        $device = NotificationDevice::first();
        $this->assertEquals($this->user1->id, $device->user_id);
        $this->assertTrue($device->is_active);

        // User 2 logs in on same browser/device and registers same token
        Sanctum::actingAs($this->user2, ['*']);
        $response = $this->postJson('/api/notifications/devices', [
            'fcm_token' => $plainToken,
            'device_type' => 'web',
            'device_name' => 'User2 PC',
            'browser' => 'Chrome',
        ]);

        $response->assertStatus(200);

        // Total rows must still be exactly 1
        $this->assertDatabaseCount('notification_devices', 1);

        // Ownership safely reassigned to User 2
        $device->refresh();
        $this->assertEquals($this->user2->id, $device->user_id);
        $this->assertEquals('User2 PC', $device->device_name);
        $this->assertTrue($device->is_active);
    }

    public function test_deactivate_device_endpoint_marks_is_active_false(): void
    {
        Sanctum::actingAs($this->user1, ['*']);
        $plainToken = 'fcm_token_sample_for_delete';

        $this->postJson('/api/notifications/devices', [
            'fcm_token' => $plainToken,
            'device_type' => 'web',
        ]);

        $this->assertTrue(NotificationDevice::first()->is_active);

        $response = $this->deleteJson('/api/notifications/devices', [
            'fcm_token' => $plainToken,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'message' => 'Notification device deactivated successfully.',
        ]);

        $this->assertFalse(NotificationDevice::first()->is_active);
    }

    public function test_user_cannot_deactivate_another_users_device(): void
    {
        $plainToken = 'user1_private_device_token';

        Sanctum::actingAs($this->user1, ['*']);
        $this->postJson('/api/notifications/devices', [
            'fcm_token' => $plainToken,
            'device_type' => 'android',
        ]);

        // User 2 attempts to deactivate User 1's device
        Sanctum::actingAs($this->user2, ['*']);
        $response = $this->deleteJson('/api/notifications/devices', [
            'fcm_token' => $plainToken,
        ]);

        $response->assertStatus(404);
        $response->assertJson([
            'status' => false,
            'message' => 'Notification device not found.',
            'error' => 'DEVICE_NOT_FOUND',
        ]);

        // Device remains active for User 1
        $this->assertTrue(NotificationDevice::first()->is_active);
    }

    public function test_user_logout_with_fcm_token_deactivates_only_that_device(): void
    {
        Sanctum::actingAs($this->user1, ['*']);
        $token1 = 'user_laptop_token';
        $token2 = 'user_phone_token';

        // Register two devices for user 1
        $this->postJson('/api/notifications/devices', [
            'fcm_token' => $token1,
            'device_type' => 'web',
        ]);

        $this->postJson('/api/notifications/devices', [
            'fcm_token' => $token2,
            'device_type' => 'android',
        ]);

        $this->assertEquals(2, $this->user1->activeNotificationDevices()->count());

        // Logout sending token1
        $response = $this->postJson('/api/user/logout', [
            'fcm_token' => $token1,
        ]);

        $response->assertStatus(200);

        // Device 1 should be deactivated, Device 2 remains active
        $d1 = NotificationDevice::where('fcm_token_hash', hash('sha256', $token1))->first();
        $d2 = NotificationDevice::where('fcm_token_hash', hash('sha256', $token2))->first();

        $this->assertFalse($d1->is_active);
        $this->assertTrue($d2->is_active);
    }
}
