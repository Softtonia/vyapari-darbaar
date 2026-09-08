<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\UserResetPasswordNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class UserPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        RateLimiter::clear('user-login');
        RateLimiter::clear('user-password-reset');
        RateLimiter::clear('user-api');
    }

    public function test_existing_active_user_can_request_password_reset(): void
    {
        Notification::fake();

        $user = User::create([
            'first_name' => 'Active',
            'last_name' => 'User',
            'name' => 'Active User',
            'phone_number' => '+919876543210',
            'username' => 'active.user',
            'email' => 'active.user@example.com',
            'password' => Hash::make('Password@12345'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/user/forgot-password', [
            'email' => 'active.user@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'A password reset link has been sent to your email address.',
            ]);

        Notification::assertSentTo(
            $user,
            UserResetPasswordNotification::class,
            function (UserResetPasswordNotification $notification, array $channels) use ($user) {
                $this->assertContains('mail', $channels);
                $this->assertEquals('redis', $notification->connection);
                $this->assertEquals('emails', $notification->queue);
                $this->assertInstanceOf(ShouldQueue::class, $notification);
                $this->assertInstanceOf(ShouldBeEncrypted::class, $notification);
                $this->assertNotEmpty($notification->token);

                $mail = $notification->toMail($user);
                $this->assertEquals('Password Reset Request - Vyapari Darbaar', $mail->subject);

                return true;
            }
        );

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'active.user@example.com',
        ]);
    }

    public function test_nonexistent_user_receives_not_found_message_and_no_email_queued(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/user/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'status' => false,
                'message' => 'No user account found with this email address.',
            ]);

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'nonexistent@example.com',
        ]);
    }

    public function test_inactive_user_receives_inactive_message_and_no_email_queued(): void
    {
        Notification::fake();

        $user = User::create([
            'first_name' => 'Inactive',
            'last_name' => 'User',
            'name' => 'Inactive User',
            'phone_number' => '+919876543211',
            'username' => 'inactive.user',
            'email' => 'inactive.user@example.com',
            'password' => Hash::make('Password@12345'),
            'status' => 'inactive',
        ]);

        $response = $this->postJson('/api/user/forgot-password', [
            'email' => 'inactive.user@example.com',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => false,
                'message' => 'Account is inactive. Please contact customer support.',
            ]);

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'inactive.user@example.com',
        ]);
    }

    public function test_reset_password_fails_with_invalid_or_expired_token(): void
    {
        $user = User::create([
            'first_name' => 'Reset',
            'last_name' => 'Tester',
            'name' => 'Reset Tester',
            'phone_number' => '+919876543212',
            'username' => 'reset.tester',
            'email' => 'reset.tester@example.com',
            'password' => Hash::make('OldPass@12345'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/user/reset-password', [
            'token' => 'invalid-token-string',
            'email' => 'reset.tester@example.com',
            'password' => 'NewStrongPass#2026',
            'password_confirmation' => 'NewStrongPass#2026',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => false,
                'message' => 'This password reset token is invalid or has expired.',
            ]);

        $this->assertTrue(Hash::check('OldPass@12345', $user->fresh()->password));
    }

    public function test_user_can_reset_password_with_valid_token_and_revokes_sessions(): void
    {
        $user = User::create([
            'first_name' => 'Success',
            'last_name' => 'Reset',
            'name' => 'Success Reset',
            'phone_number' => '+919876543213',
            'username' => 'success.reset',
            'email' => 'success.reset@example.com',
            'password' => Hash::make('OldPassword#123'),
            'status' => 'active',
            'must_change_password' => true,
        ]);

        $user->assignRole('user');
        $oldSessionToken = $user->createToken('old-device')->plainTextToken;

        $token = Password::broker('users')->createToken($user);

        $response = $this->postJson('/api/user/reset-password', [
            'token' => $token,
            'email' => 'success.reset@example.com',
            'password' => 'BrandNewPassword#2026',
            'password_confirmation' => 'BrandNewPassword#2026',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Your password has been reset successfully.',
            ]);

        $freshUser = $user->fresh();
        $this->assertTrue(Hash::check('BrandNewPassword#2026', $freshUser->password));
        $this->assertFalse($freshUser->must_change_password);

        // Previous device sessions are revoked
        $this->assertEquals(0, $freshUser->tokens()->count());

        $this->withToken($oldSessionToken)
            ->getJson('/api/user/profile')
            ->assertStatus(401);

        // Login with new password succeeds
        $loginResponse = $this->postJson('/api/user/login', [
            'username' => 'success.reset',
            'password' => 'BrandNewPassword#2026',
        ])->assertStatus(200);

        $this->assertTrue($loginResponse->json('status'));
    }
}
