<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\NotificationDevice;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNotificationDeviceTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
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
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Accept' => 'application/json',
        ];
    }

    public function test_admin_can_paginate_devices_with_masked_token_and_ip(): void
    {
        $user = User::factory()->create(['name' => 'Kishore Kumar', 'email' => 'kishore@example.com']);
        $rawToken = 'eJYQfXU1234567890abcdefghijklmnopqrstuvwxyzA9TU';

        NotificationDevice::create([
            'user_id' => $user->id,
            'fcm_token' => $rawToken,
            'fcm_token_hash' => hash('sha256', $rawToken),
            'device_type' => 'android',
            'device_name' => 'Samsung Galaxy S24',
            'browser' => 'Chrome Mobile',
            'ip_address' => '192.168.1.50',
            'is_active' => true,
            'last_used_at' => now(),
        ]);

        $response = $this->getJson('/api/admin/notifications/devices?search=Samsung', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.device_name', 'Samsung Galaxy S24')
            ->assertJsonPath('data.data.0.ip_address', '192.168.1.50')
            ->assertJsonPath('data.data.0.masked_token', 'eJYQfXU*************A9TU');

        // Verify raw token and token hash are never in response
        $this->assertStringNotContainsString($rawToken, $response->getContent());
        $this->assertStringNotContainsString(hash('sha256', $rawToken), $response->getContent());
    }

    public function test_admin_can_toggle_device_status_and_delete(): void
    {
        $user = User::factory()->create();
        $device = NotificationDevice::create([
            'user_id' => $user->id,
            'fcm_token' => 'sample_token_123456789',
            'fcm_token_hash' => hash('sha256', 'sample_token_123456789'),
            'device_type' => 'ios',
            'device_name' => 'iPhone 15',
            'is_active' => true,
        ]);

        // Toggle status
        $patchResponse = $this->patchJson("/api/admin/notifications/devices/{$device->id}/status", [
            'is_active' => false,
        ], $this->authHeaders());

        $patchResponse->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('notification_devices', [
            'id' => $device->id,
            'is_active' => false,
        ]);

        // Delete device
        $deleteResponse = $this->deleteJson("/api/admin/notifications/devices/{$device->id}", [], $this->authHeaders());
        $deleteResponse->assertOk();

        $this->assertDatabaseMissing('notification_devices', ['id' => $device->id]);
    }

    public function test_admin_can_bulk_delete_devices(): void
    {
        $user = User::factory()->create();
        $d1 = NotificationDevice::create([
            'user_id' => $user->id,
            'fcm_token' => 'token_1_123456789',
            'fcm_token_hash' => hash('sha256', 'token_1_123456789'),
            'device_type' => 'web',
        ]);
        $d2 = NotificationDevice::create([
            'user_id' => $user->id,
            'fcm_token' => 'token_2_123456789',
            'fcm_token_hash' => hash('sha256', 'token_2_123456789'),
            'device_type' => 'web',
        ]);

        $response = $this->deleteJson('/api/admin/notifications/devices/bulk', [
            'ids' => [$d1->id, $d2->id],
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.deleted_count', 2);

        $this->assertDatabaseMissing('notification_devices', ['id' => $d1->id]);
        $this->assertDatabaseMissing('notification_devices', ['id' => $d2->id]);
    }
}
