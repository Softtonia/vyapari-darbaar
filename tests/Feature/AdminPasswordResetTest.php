<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use App\Notifications\AdminResetPasswordNotification;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AdminPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('admin-login');
        RateLimiter::clear('admin-password-reset');
        RateLimiter::clear('admin-api');
    }

    public function test_existing_active_admin_can_request_password_reset(): void
    {
        Notification::fake();

        $admin = Admin::create([
            'name' => 'Active Admin',
            'email' => 'active.admin@example.com',
            'password' => Hash::make('Password@12345'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/admin/forgot-password', [
            'email' => 'active.admin@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'A password reset link has been sent to your email address.',
            ]);

        Notification::assertSentTo(
            $admin,
            AdminResetPasswordNotification::class,
            function (AdminResetPasswordNotification $notification, array $channels) use ($admin) {
                $this->assertContains('mail', $channels);
                $this->assertEquals('redis', $notification->connection);
                $this->assertEquals('emails', $notification->queue);
                $this->assertInstanceOf(ShouldQueue::class, $notification);
                $this->assertInstanceOf(ShouldBeEncrypted::class, $notification);
                $this->assertNotEmpty($notification->token);

                $mail = $notification->toMail($admin);
                $this->assertEquals('Admin Password Reset Request', $mail->subject);

                return true;
            }
        );

        $this->assertDatabaseHas('admin_password_reset_tokens', [
            'email' => 'active.admin@example.com',
        ]);
    }

    public function test_nonexistent_admin_receives_not_found_message_and_no_email_queued(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/admin/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'status' => false,
                'message' => 'No administrator account found with this email address.',
            ]);

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('admin_password_reset_tokens', [
            'email' => 'nonexistent@example.com',
        ]);
    }

    public function test_inactive_admin_receives_inactive_message_and_no_email_queued(): void
    {
        Notification::fake();

        $admin = Admin::create([
            'name' => 'Inactive Admin',
            'email' => 'inactive.admin@example.com',
            'password' => Hash::make('Password@12345'),
            'status' => 'inactive',
        ]);

        $response = $this->postJson('/api/admin/forgot-password', [
            'email' => 'inactive.admin@example.com',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => false,
                'message' => 'Account is inactive. Please contact the administrator.',
            ]);

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('admin_password_reset_tokens', [
            'email' => 'inactive.admin@example.com',
        ]);
    }

    public function test_notification_configuration_and_encryption_contract(): void
    {
        $notification = new AdminResetPasswordNotification('sample-token');

        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertInstanceOf(ShouldBeEncrypted::class, $notification);
        $this->assertEquals('redis', $notification->connection);
        $this->assertEquals('emails', $notification->queue);
        $this->assertEquals(3, $notification->tries);
        $this->assertEquals(60, $notification->timeout);
        $this->assertEquals([10, 30, 60], $notification->backoff);
    }

    public function test_valid_token_resets_password_and_revokes_all_sanctum_tokens(): void
    {
        $admin = Admin::create([
            'name' => 'Reset Admin',
            'email' => 'reset.admin@example.com',
            'password' => Hash::make('OldPassword@123'),
            'status' => 'active',
        ]);

        // Issue 2 active Sanctum tokens
        $token1 = $admin->createToken('Device 1')->plainTextToken;
        $token2 = $admin->createToken('Device 2')->plainTextToken;
        $this->assertEquals(2, $admin->tokens()->count());

        // Create password reset token using the admins broker
        $rawToken = Password::broker('admins')->createToken($admin);

        $response = $this->postJson('/api/admin/reset-password', [
            'email' => 'reset.admin@example.com',
            'token' => $rawToken,
            'password' => 'NewSecurePassword#2026',
            'password_confirmation' => 'NewSecurePassword#2026',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Your password has been reset successfully.',
            ]);

        // Verify all Sanctum tokens revoked
        $this->assertEquals(0, $admin->fresh()->tokens()->count());

        // Verify old tokens no longer authenticate
        auth()->forgetGuards();
        $this->withToken($token1)->postJson('/api/admin/logout')->assertStatus(401);
        auth()->forgetGuards();
        $this->withToken($token2)->postJson('/api/admin/logout')->assertStatus(401);

        // Verify old password fails login
        $this->postJson('/api/admin/login', [
            'email' => 'reset.admin@example.com',
            'password' => 'OldPassword@123',
        ])->assertStatus(401);

        // Verify new password succeeds login
        $this->postJson('/api/admin/login', [
            'email' => 'reset.admin@example.com',
            'password' => 'NewSecurePassword#2026',
        ])->assertStatus(200);

        // Verify reset token in admin_password_reset_tokens was consumed/deleted
        $this->assertDatabaseMissing('admin_password_reset_tokens', [
            'email' => 'reset.admin@example.com',
        ]);
    }

    public function test_expired_token_is_rejected(): void
    {
        $admin = Admin::create([
            'name' => 'Expire Admin',
            'email' => 'expire@example.com',
            'password' => Hash::make('OldPassword@123'),
            'status' => 'active',
        ]);

        $rawToken = Password::broker('admins')->createToken($admin);

        // Travel 31 minutes into the future (broker expire is 30 minutes)
        Carbon::setTestNow(now()->addMinutes(31));

        $response = $this->postJson('/api/admin/reset-password', [
            'email' => 'expire@example.com',
            'token' => $rawToken,
            'password' => 'NewSecurePassword#2026',
            'password_confirmation' => 'NewSecurePassword#2026',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => false,
                'message' => 'This password reset token is invalid or has expired.',
            ]);

        Carbon::setTestNow(); // Reset time
    }

    public function test_invalid_token_is_rejected(): void
    {
        Admin::create([
            'name' => 'Invalid Token Admin',
            'email' => 'invalid@example.com',
            'password' => Hash::make('OldPassword@123'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/admin/reset-password', [
            'email' => 'invalid@example.com',
            'token' => 'completely-bogus-token',
            'password' => 'NewSecurePassword#2026',
            'password_confirmation' => 'NewSecurePassword#2026',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => false,
                'message' => 'This password reset token is invalid or has expired.',
            ]);
    }

    public function test_reused_token_is_rejected(): void
    {
        $admin = Admin::create([
            'name' => 'Reused Admin',
            'email' => 'reused@example.com',
            'password' => Hash::make('OldPassword@123'),
            'status' => 'active',
        ]);

        $rawToken = Password::broker('admins')->createToken($admin);

        // First reset
        $this->postJson('/api/admin/reset-password', [
            'email' => 'reused@example.com',
            'token' => $rawToken,
            'password' => 'NewSecurePassword#2026',
            'password_confirmation' => 'NewSecurePassword#2026',
        ])->assertStatus(200);

        // Second reset with same token
        $response = $this->postJson('/api/admin/reset-password', [
            'email' => 'reused@example.com',
            'token' => $rawToken,
            'password' => 'AnotherPassword#2026',
            'password_confirmation' => 'AnotherPassword#2026',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => false,
                'message' => 'This password reset token is invalid or has expired.',
            ]);
    }

    public function test_weak_password_is_rejected(): void
    {
        $admin = Admin::create([
            'name' => 'Weak Admin',
            'email' => 'weak@example.com',
            'password' => Hash::make('OldPassword@123'),
            'status' => 'active',
        ]);

        $rawToken = Password::broker('admins')->createToken($admin);

        // Missing symbol, numbers, uppercase, too short
        $weakPasswords = [
            'short',           // Too short
            'alllowercase123#', // Missing uppercase
            'ALLUPPERCASE123#', // Missing lowercase
            'NoNumberSymbol!',  // Missing number
            'NoSymbol123456',   // Missing symbol
        ];

        foreach ($weakPasswords as $weakPass) {
            $response = $this->postJson('/api/admin/reset-password', [
                'email' => 'weak@example.com',
                'token' => $rawToken,
                'password' => $weakPass,
                'password_confirmation' => $weakPass,
            ]);

            $response->assertStatus(422)
                ->assertJson([
                    'status' => false,
                    'message' => 'Validation error.',
                ]);
        }
    }

    public function test_password_confirmation_mismatch_is_rejected(): void
    {
        $admin = Admin::create([
            'name' => 'Mismatch Admin',
            'email' => 'mismatch@example.com',
            'password' => Hash::make('OldPassword@123'),
            'status' => 'active',
        ]);

        $rawToken = Password::broker('admins')->createToken($admin);

        $response = $this->postJson('/api/admin/reset-password', [
            'email' => 'mismatch@example.com',
            'token' => $rawToken,
            'password' => 'NewSecurePassword#2026',
            'password_confirmation' => 'DifferentPassword#2026',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
                'message' => 'Validation error.',
            ]);
    }

    public function test_user_cannot_use_admin_password_broker_or_table(): void
    {
        $user = User::create([
            'name' => 'User Token Test',
            'username' => 'usertest',
            'email' => 'user@example.com',
            'password' => Hash::make('UserPassword@123'),
            'status' => 'active',
        ]);

        // Attempting to create token with user model via admins broker must fail or return null
        $this->assertNull(Password::broker('admins')->getUser(['email' => 'user@example.com']));

        // Attempting to reset password through admin endpoint with a user email
        $response = $this->postJson('/api/admin/reset-password', [
            'email' => 'user@example.com',
            'token' => 'some-token',
            'password' => 'NewSecurePassword#2026',
            'password_confirmation' => 'NewSecurePassword#2026',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => false,
                'message' => 'This password reset token is invalid or has expired.',
            ]);
    }

    public function test_admin_forgot_password_rate_limiting(): void
    {
        Admin::create([
            'name' => 'Throttle Admin',
            'email' => 'throttle.admin@example.com',
            'password' => Hash::make('Password@12345'),
            'status' => 'active',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/admin/forgot-password', [
                'email' => 'throttle.admin@example.com',
            ])->assertStatus(200);
        }

        // 6th attempt is throttled
        $response = $this->postJson('/api/admin/forgot-password', [
            'email' => 'throttle.admin@example.com',
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'status' => false,
                'message' => 'Too many password reset attempts. Please try again later.',
            ]);
    }

    public function test_sensitive_fields_and_tokens_are_never_exposed(): void
    {
        Admin::create([
            'name' => 'Safe Admin',
            'email' => 'safe@example.com',
            'password' => Hash::make('Password@12345'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/admin/forgot-password', [
            'email' => 'safe@example.com',
        ]);

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('token', (array) $response->json('data'));
        $this->assertArrayNotHasKey('password', (array) $response->json('data'));
        $this->assertEmpty((array) $response->json('data'));
    }
}
