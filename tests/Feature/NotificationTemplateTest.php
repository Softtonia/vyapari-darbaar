<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\NotificationTemplate;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationTemplateService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTemplateTest extends TestCase
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

    public function test_admin_can_paginate_and_search_templates(): void
    {
        NotificationTemplate::create([
            'name' => 'Order Confirmation',
            'code' => 'ORDER_CONFIRM',
            'title' => 'Order Confirmed',
            'body' => 'Your order is confirmed.',
            'channel' => 'push',
            'status' => true,
        ]);
        NotificationTemplate::create([
            'name' => 'Welcome Offer',
            'code' => 'WELCOME_OFFER',
            'title' => 'Welcome!',
            'body' => 'Welcome to Vyapari Darbaar.',
            'channel' => 'in_app',
            'status' => true,
        ]);

        $response = $this->getJson('/api/admin/notifications/templates?search=Order', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.code', 'ORDER_CONFIRM');
    }

    public function test_admin_can_create_template(): void
    {
        $payload = [
            'name' => 'Payment Received',
            'code' => 'PAYMENT_RECEIVED',
            'title' => 'Payment received',
            'body' => 'Hello {{user_name}}, we received your payment.',
            'channel' => 'push_and_in_app',
            'status' => true,
        ];

        $response = $this->postJson('/api/admin/notifications/templates', $payload, $this->authHeaders());

        $response->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.code', 'PAYMENT_RECEIVED');

        $this->assertDatabaseHas('notification_templates', ['code' => 'PAYMENT_RECEIVED']);
    }

    public function test_admin_can_update_and_delete_template(): void
    {
        $template = NotificationTemplate::create([
            'name' => 'Old Name',
            'code' => 'TEMP_1',
            'title' => 'Old Title',
            'body' => 'Old Body',
            'channel' => 'push',
            'status' => true,
        ]);

        $updateResponse = $this->putJson("/api/admin/notifications/templates/{$template->id}", [
            'name' => 'New Name',
            'code' => 'TEMP_1',
            'title' => 'New Title',
            'body' => 'New Body',
            'channel' => 'push',
            'status' => true,
        ], $this->authHeaders());

        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $deleteResponse = $this->deleteJson("/api/admin/notifications/templates/{$template->id}", [], $this->authHeaders());
        $deleteResponse->assertOk();

        $this->assertDatabaseMissing('notification_templates', ['id' => $template->id]);
    }

    public function test_admin_can_bulk_delete_templates(): void
    {
        $t1 = NotificationTemplate::create([
            'name' => 'T1',
            'code' => 'B_1',
            'title' => 'Title 1',
            'body' => 'Body 1',
            'channel' => 'push',
            'status' => true,
        ]);
        $t2 = NotificationTemplate::create([
            'name' => 'T2',
            'code' => 'B_2',
            'title' => 'Title 2',
            'body' => 'Body 2',
            'channel' => 'push',
            'status' => true,
        ]);

        $response = $this->deleteJson('/api/admin/notifications/templates/bulk', [
            'ids' => [$t1->id, $t2->id],
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.deleted_count', 2);

        $this->assertDatabaseMissing('notification_templates', ['id' => $t1->id]);
    }

    public function test_safe_placeholder_renderer_renders_without_eval(): void
    {
        $service = app(NotificationTemplateService::class);
        $user = User::factory()->create(['name' => 'Amit Kumar', 'first_name' => 'Amit', 'last_name' => 'Kumar']);

        $vars = $service->buildUserVariables($user);
        $rendered = $service->render('Hello {{user_name}}, welcome to {{app_name}}!', $vars);

        $this->assertStringContainsString('Hello Amit Kumar', $rendered);
    }
}
