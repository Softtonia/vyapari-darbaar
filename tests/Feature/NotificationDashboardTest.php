<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Models\Admin;
use App\Models\InAppNotification;
use App\Models\NotificationDevice;
use App\Models\NotificationLog;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationDashboardService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class NotificationDashboardTest extends TestCase
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
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Accept' => 'application/json',
        ];
    }

    public function test_admin_can_view_dashboard_metrics_and_charts(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        NotificationDevice::create([
            'user_id' => $user->id,
            'fcm_token' => 'fcm_dash_1',
            'fcm_token_hash' => hash('sha256', 'fcm_dash_1'),
            'device_type' => 'android',
            'is_active' => true,
        ]);

        NotificationLog::create([
            'user_id' => $user->id,
            'channel' => 'push',
            'title' => 'Test Sent',
            'body' => 'Body Sent',
            'status' => DeliveryStatus::SENT,
            'sent_at' => now(),
        ]);

        NotificationLog::create([
            'user_id' => $user->id,
            'channel' => 'push',
            'title' => 'Test Failed',
            'body' => 'Body Failed',
            'status' => DeliveryStatus::FAILED,
            'error_code' => 'INVALID_TOKEN',
            'failed_at' => now(),
        ]);

        InAppNotification::create([
            'user_id' => $user->id,
            'title' => 'Unread Message',
            'body' => 'Unread message body',
            'read_at' => null,
        ]);

        $response = $this->getJson('/api/admin/notifications/dashboard?days=30', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.sent_today', 1)
            ->assertJsonPath('data.failed_today', 1)
            ->assertJsonPath('data.active_devices', 1)
            ->assertJsonPath('data.users_with_push', 1)
            ->assertJsonPath('data.unread_in_app', 1)
            ->assertJsonPath('data.device_platform_distribution.android', 1)
            ->assertJsonCount(30, 'data.chart_data');
    }

    public function test_dashboard_uses_cache_and_refreshes_on_demand(): void
    {
        $response1 = $this->getJson('/api/admin/notifications/dashboard', $this->authHeaders());
        $response1->assertOk();

        // Check that cache key exists
        $this->assertTrue(Cache::has(NotificationDashboardService::CACHE_KEY . ':days_30'));

        // Request with refresh=true
        $response2 = $this->getJson('/api/admin/notifications/dashboard?refresh=true', $this->authHeaders());
        $response2->assertOk();
    }
}
