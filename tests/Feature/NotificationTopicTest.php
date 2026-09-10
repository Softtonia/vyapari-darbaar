<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\NotificationTopic;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTopicTest extends TestCase
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

    public function test_admin_can_crud_topics(): void
    {
        // Create
        $response = $this->postJson('/api/admin/notifications/topics', [
            'name' => 'Wheat Traders',
            'slug' => 'wheat-traders',
            'description' => 'Traders of wheat commodity',
            'status' => true,
        ], $this->authHeaders());

        $response->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.slug', 'wheat-traders');

        $topicId = $response->json('data.id');

        // Show
        $showResponse = $this->getJson("/api/admin/notifications/topics/{$topicId}", $this->authHeaders());
        $showResponse->assertOk()
            ->assertJsonPath('data.name', 'Wheat Traders');

        // Update
        $updateResponse = $this->putJson("/api/admin/notifications/topics/{$topicId}", [
            'name' => 'Premium Wheat Traders',
            'slug' => 'premium-wheat-traders',
        ], $this->authHeaders());
        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'Premium Wheat Traders');

        // Delete
        $deleteResponse = $this->deleteJson("/api/admin/notifications/topics/{$topicId}", [], $this->authHeaders());
        $deleteResponse->assertOk();

        $this->assertDatabaseMissing('notification_topics', ['id' => $topicId]);
    }

    public function test_admin_can_bulk_add_and_remove_topic_users(): void
    {
        $topic = NotificationTopic::create([
            'name' => 'Mustard Traders',
            'slug' => 'mustard-traders',
            'status' => true,
        ]);

        $u1 = User::factory()->create(['status' => 'active']);
        $u2 = User::factory()->create(['status' => 'active']);
        $u3 = User::factory()->create(['status' => 'active']);

        // Add users
        $addResponse = $this->postJson("/api/admin/notifications/topics/{$topic->id}/users", [
            'user_ids' => [$u1->id, $u2->id, $u3->id],
        ], $this->authHeaders());

        $addResponse->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.added_count', 3);

        $this->assertDatabaseHas('notification_topic_users', [
            'notification_topic_id' => $topic->id,
            'user_id' => $u1->id,
        ]);

        // Remove users
        $removeResponse = $this->deleteJson("/api/admin/notifications/topics/{$topic->id}/users", [
            'user_ids' => [$u1->id, $u2->id],
        ], $this->authHeaders());

        $removeResponse->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.removed_count', 2);

        $this->assertDatabaseMissing('notification_topic_users', [
            'notification_topic_id' => $topic->id,
            'user_id' => $u1->id,
        ]);
        $this->assertDatabaseHas('notification_topic_users', [
            'notification_topic_id' => $topic->id,
            'user_id' => $u3->id,
        ]);
    }
}
