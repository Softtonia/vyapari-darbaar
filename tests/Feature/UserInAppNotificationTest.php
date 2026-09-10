<?php

namespace Tests\Feature;

use App\Models\InAppNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserInAppNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_paginated_notifications_and_unread_count(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        InAppNotification::create([
            'user_id' => $user->id,
            'title' => 'Message 1',
            'body' => 'Body 1',
            'read_at' => null,
        ]);
        InAppNotification::create([
            'user_id' => $user->id,
            'title' => 'Message 2',
            'body' => 'Body 2',
            'read_at' => now(),
        ]);

        // List
        $listResponse = $this->getJson('/api/notifications');
        $listResponse->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.unread_count', 1);

        // Unread count endpoint
        $countResponse = $this->getJson('/api/notifications/unread-count');
        $countResponse->assertOk()
            ->assertJsonPath('data.unread_count', 1);
    }

    public function test_user_can_mark_single_and_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $n1 = InAppNotification::create(['user_id' => $user->id, 'title' => 'N1', 'body' => 'B1', 'read_at' => null]);
        $n2 = InAppNotification::create(['user_id' => $user->id, 'title' => 'N2', 'body' => 'B2', 'read_at' => null]);

        // Mark single
        $readResponse = $this->patchJson("/api/notifications/{$n1->id}/read");
        $readResponse->assertOk()
            ->assertJsonPath('data.is_read', true);

        $n1->refresh();
        $this->assertNotNull($n1->read_at);

        // Mark all
        $readAllResponse = $this->patchJson('/api/notifications/read-all');
        $readAllResponse->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $n2->refresh();
        $this->assertNotNull($n2->read_at);
    }

    public function test_user_cannot_read_or_delete_another_users_notification(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $notificationB = InAppNotification::create([
            'user_id' => $userB->id,
            'title' => 'Private message for B',
            'body' => 'Confidential',
            'read_at' => null,
        ]);

        Sanctum::actingAs($userA, ['*']);

        // Attempt read
        $readResponse = $this->patchJson("/api/notifications/{$notificationB->id}/read");
        $readResponse->assertStatus(404);

        // Attempt delete
        $deleteResponse = $this->deleteJson("/api/notifications/{$notificationB->id}");
        $deleteResponse->assertStatus(404);

        $this->assertDatabaseHas('in_app_notifications', ['id' => $notificationB->id]);
    }
}
