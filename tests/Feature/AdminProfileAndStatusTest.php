<?php

namespace Tests\Feature;

use App\Jobs\SendUserCredentialsEmailJob;
use App\Models\Admin;
use App\Models\EmailTemplate;
use App\Models\User;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AdminProfileAndStatusTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected string $adminPassword = 'AdminPassword#2026';

    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('admin-api');
        RateLimiter::clear('admin-login');
        RateLimiter::clear('admin-user-resend-credentials');

        $this->seed(EmailTemplateSeeder::class);

        $this->admin = Admin::create([
            'name' => 'Main Admin',
            'email' => 'main.admin@example.com',
            'password' => Hash::make($this->adminPassword),
            'status' => 'active',
        ]);

        $this->adminToken = $this->admin->createToken('admin-primary')->plainTextToken;
    }

    public function test_active_admin_can_retrieve_profile_without_sensitive_data(): void
    {
        $response = $this->withToken($this->adminToken)->getJson('/api/admin/profile');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Admin profile retrieved successfully.',
                'data' => [
                    'id' => $this->admin->id,
                    'name' => 'Main Admin',
                    'email' => 'main.admin@example.com',
                    'status' => 'active',
                ],
            ]);

        $data = $response->json('data');
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('tokens', $data);
    }

    public function test_unauthenticated_and_user_tokens_are_blocked_from_admin_profile(): void
    {
        $this->getJson('/api/admin/profile')->assertStatus(401);
        $this->patchJson('/api/admin/profile', ['name' => 'Test'])->assertStatus(401);

        $user = User::create([
            'name' => 'Regular User',
            'username' => 'reg.user',
            'email' => 'reg.user@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $userToken = $user->createToken('user-token')->plainTextToken;

        $this->withToken($userToken)->getJson('/api/admin/profile')->assertStatus(403);
        $this->withToken($userToken)->patchJson('/api/admin/profile', ['name' => 'Test'])->assertStatus(403);
    }

    public function test_inactive_admin_cannot_access_profile(): void
    {
        $this->admin->update(['status' => 'inactive']);

        $this->withToken($this->adminToken)
            ->getJson('/api/admin/profile')
            ->assertStatus(403);
    }

    public function test_admin_can_update_name_without_current_password(): void
    {
        $response = $this->withToken($this->adminToken)->patchJson('/api/admin/profile', [
            'name' => 'Updated Admin Name',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'name' => 'Updated Admin Name',
                    'email' => 'main.admin@example.com',
                ],
            ]);

        $this->assertEquals('Updated Admin Name', $this->admin->fresh()->name);
    }

    public function test_admin_email_change_requires_valid_current_password_and_cleans_reset_tokens(): void
    {
        // 1. Insert a stale password reset token for current email
        DB::table('admin_password_reset_tokens')->insert([
            'email' => 'main.admin@example.com',
            'token' => Hash::make('stale-reset-token'),
            'created_at' => now(),
        ]);

        // 2. Issue a secondary admin token
        $secondaryToken = $this->admin->createToken('admin-secondary')->plainTextToken;
        $this->assertEquals(2, $this->admin->tokens()->count());

        // 3. Attempt email change without current password -> 422
        $this->withToken($this->adminToken)->patchJson('/api/admin/profile', [
            'email' => 'new.admin@example.com',
        ])->assertStatus(422)->assertJsonValidationErrors(['current_password']);

        // 4. Attempt email change with wrong current password -> 422
        $this->withToken($this->adminToken)->patchJson('/api/admin/profile', [
            'email' => 'new.admin@example.com',
            'current_password' => 'WrongPassword#999',
        ])->assertStatus(422)->assertJsonValidationErrors(['current_password']);

        // 5. Valid email change with correct current password -> 200
        $response = $this->withToken($this->adminToken)->patchJson('/api/admin/profile', [
            'email' => 'new.admin@example.com',
            'current_password' => $this->adminPassword,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'email' => 'new.admin@example.com',
                ],
            ]);

        // Verify stale reset token was deleted
        $this->assertDatabaseMissing('admin_password_reset_tokens', ['email' => 'main.admin@example.com']);

        // Verify current token survives
        auth()->forgetGuards();
        $this->withToken($this->adminToken)->getJson('/api/admin/profile')->assertStatus(200);

        // Verify secondary token was revoked
        auth()->forgetGuards();
        $this->withToken($secondaryToken)->getJson('/api/admin/profile')->assertStatus(401);

        // Verify new email authenticates for login
        $this->postJson('/api/admin/login', [
            'email' => 'new.admin@example.com',
            'password' => $this->adminPassword,
        ])->assertStatus(200);

        // Old email fails login
        $this->postJson('/api/admin/login', [
            'email' => 'main.admin@example.com',
            'password' => $this->adminPassword,
        ])->assertStatus(401);
    }

    public function test_admin_cannot_change_email_to_existing_admin_email(): void
    {
        Admin::create([
            'name' => 'Other Admin',
            'email' => 'other.admin@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $this->withToken($this->adminToken)->patchJson('/api/admin/profile', [
            'email' => 'other.admin@example.com',
            'current_password' => $this->adminPassword,
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_user_status_management_transitions_and_token_revocation(): void
    {
        $user = User::create([
            'name' => 'Status User',
            'username' => 'status.user',
            'email' => 'status.user@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $userToken = $user->createToken('user-device')->plainTextToken;
        $this->assertEquals(1, $user->tokens()->count());

        // Invalid status rejected
        $this->withToken($this->adminToken)
            ->patchJson("/api/admin/users/{$user->id}/status", ['status' => 'unknown'])
            ->assertStatus(422);

        // Transition to inactive revokes all tokens
        $resInactive = $this->withToken($this->adminToken)
            ->patchJson("/api/admin/users/{$user->id}/status", ['status' => 'inactive']);

        $resInactive->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'status' => 'inactive',
                ],
            ]);

        $this->assertEquals('inactive', $user->fresh()->status);
        $this->assertEquals(0, $user->tokens()->count());

        // Token cannot access User API after deactivation
        auth()->forgetGuards();
        $this->withToken($userToken)->getJson('/api/user/profile')->assertStatus(401);

        // Transition to suspended
        $this->withToken($this->adminToken)
            ->patchJson("/api/admin/users/{$user->id}/status", ['status' => 'suspended'])
            ->assertStatus(200);
        $this->assertEquals('suspended', $user->fresh()->status);

        // Transition back to active does not issue tokens or change credentials
        $this->withToken($this->adminToken)
            ->patchJson("/api/admin/users/{$user->id}/status", ['status' => 'active'])
            ->assertStatus(200);
        $this->assertEquals('active', $user->fresh()->status);
        $this->assertEquals(0, $user->tokens()->count());
    }

    public function test_credential_resend_generates_new_temporary_password_and_queues_email(): void
    {
        Queue::fake();

        $user = User::create([
            'name' => 'Resend User',
            'username' => 'resend.user',
            'email' => 'resend.user@example.com',
            'password' => Hash::make('OldUserPassword#123'),
            'status' => 'active',
            'must_change_password' => false,
        ]);

        $oldToken = $user->createToken('old-device')->plainTextToken;
        $this->assertEquals(1, $user->tokens()->count());

        $response = $this->withToken($this->adminToken)
            ->postJson("/api/admin/users/{$user->id}/resend-credentials");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Credentials resent successfully.',
            ]);

        $user->refresh();
        // Username remains untouched
        $this->assertEquals('resend.user', $user->username);
        $this->assertEquals('resend.user@example.com', $user->email);
        $this->assertTrue($user->must_change_password);
        $this->assertFalse(Hash::check('OldUserPassword#123', $user->password));

        // Existing tokens revoked
        $this->assertEquals(0, $user->tokens()->count());

        // Job pushed
        Queue::assertPushed(
            SendUserCredentialsEmailJob::class,
            function (SendUserCredentialsEmailJob $job) use ($user) {
                $this->assertEquals($user->id, $job->userId);
                $this->assertEquals('resend.user@example.com', $job->recipientEmail);
                $this->assertEquals('redis', $job->connection);
                $this->assertEquals('emails', $job->queue);
                $this->assertInstanceOf(ShouldQueue::class, $job);
                $this->assertInstanceOf(ShouldBeEncrypted::class, $job);

                // Check rendered snapshot
                $this->assertStringContainsString('Resend User', $job->renderedBody);
                $this->assertStringContainsString('resend.user', $job->renderedBody);
                $this->assertStringNotContainsString('{{Username}}', $job->renderedBody);
                $this->assertStringNotContainsString('{{TemporaryPassword}}', $job->renderedBody);
                $this->assertStringNotContainsString('{{TemporaryPassword}}', $job->renderedSubject);

                return true;
            }
        );
    }

    public function test_credential_resend_fails_if_user_is_inactive_or_suspended(): void
    {
        Queue::fake();

        $user = User::create([
            'name' => 'Inactive Resend',
            'username' => 'inactive.resend',
            'email' => 'inactive.resend@example.com',
            'password' => Hash::make('password'),
            'status' => 'inactive',
        ]);

        $this->withToken($this->adminToken)
            ->postJson("/api/admin/users/{$user->id}/resend-credentials")
            ->assertStatus(422)
            ->assertJson([
                'status' => false,
                'message' => 'Cannot resend credentials to an inactive or suspended user. Please activate the user first.',
            ]);

        Queue::assertNothingPushed();

        $user->update(['status' => 'suspended']);

        $this->withToken($this->adminToken)
            ->postJson("/api/admin/users/{$user->id}/resend-credentials")
            ->assertStatus(422);

        Queue::assertNothingPushed();
    }

    public function test_credential_resend_fails_if_template_is_missing_or_inactive(): void
    {
        Queue::fake();

        $user = User::create([
            'name' => 'Template Resend User',
            'username' => 'tpl.resend',
            'email' => 'tpl.resend@example.com',
            'password' => Hash::make('OriginalPass#123'),
            'status' => 'active',
        ]);

        $template = EmailTemplate::where('key', 'USER_ACCOUNT_CREATED')->first();
        $template->update(['is_active' => false]);

        $this->withToken($this->adminToken)
            ->postJson("/api/admin/users/{$user->id}/resend-credentials")
            ->assertStatus(422);

        Queue::assertNothingPushed();
        $this->assertTrue(Hash::check('OriginalPass#123', $user->fresh()->password));
    }

    public function test_credential_resend_rate_limiter(): void
    {
        Queue::fake();

        $user = User::create([
            'name' => 'Rate User',
            'username' => 'rate.user',
            'email' => 'rate.user@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        // 3 requests allowed within 10 minutes
        for ($i = 1; $i <= 3; $i++) {
            $this->withToken($this->adminToken)
                ->postJson("/api/admin/users/{$user->id}/resend-credentials")
                ->assertStatus(200);
        }

        // 4th attempt is throttled
        $this->withToken($this->adminToken)
            ->postJson("/api/admin/users/{$user->id}/resend-credentials")
            ->assertStatus(429)
            ->assertJson([
                'status' => false,
                'message' => 'Too many credential resend requests for this user. Please try again later.',
            ]);
    }
}
