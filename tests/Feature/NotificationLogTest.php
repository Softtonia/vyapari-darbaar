<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Models\Admin;
use App\Models\NotificationLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationLogTest extends TestCase
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

    public function test_admin_can_paginate_and_filter_logs(): void
    {
        $user = User::factory()->create(['name' => 'Sanjay Gupta']);

        NotificationLog::create([
            'user_id' => $user->id,
            'channel' => 'push',
            'title' => 'Important Alert for Sanjay',
            'body' => 'Body text',
            'status' => DeliveryStatus::SENT,
            'sent_at' => now(),
        ]);

        NotificationLog::create([
            'user_id' => $user->id,
            'channel' => 'in_app',
            'title' => 'In-app Notice',
            'body' => 'Body text',
            'status' => DeliveryStatus::SENT,
            'sent_at' => now(),
        ]);

        $response = $this->getJson('/api/admin/notifications/logs?channel=push', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.channel', 'push');
    }

    public function test_notification_log_survives_user_deletion_with_null_user_id(): void
    {
        $user = User::factory()->create();

        $log = NotificationLog::create([
            'user_id' => $user->id,
            'channel' => 'push',
            'title' => 'Audit Log Test',
            'body' => 'Body',
            'status' => DeliveryStatus::SENT,
        ]);

        // Delete user
        $user->delete();

        $log->refresh();

        $this->assertNull($log->user_id);
        $this->assertDatabaseHas('notification_logs', ['id' => $log->id]);
    }
}
