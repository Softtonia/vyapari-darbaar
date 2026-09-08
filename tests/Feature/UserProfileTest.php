<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\UserEmailUpdateOtpNotification;
use App\Services\OtpService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $userToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->user = User::create([
            'first_name' => 'Rahul',
            'last_name' => 'Sharma',
            'name' => 'Rahul Sharma',
            'phone_number' => '+919876543210',
            'username' => 'rahul.sharma',
            'email' => 'rahul.sharma@example.com',
            'password' => Hash::make('UserPass@12345'),
            'status' => 'active',
            'must_change_password' => false,
        ]);

        $this->user->assignRole('user');
        $this->userToken = $this->user->createToken('user-device-1')->plainTextToken;
    }

    public function test_unauthenticated_user_cannot_access_profile_endpoints(): void
    {
        $this->getJson('/api/user/profile')->assertStatus(401);
        $this->postJson('/api/user/profile/send-email-otp', ['email' => 'new@example.com'])->assertStatus(401);
        $this->patchJson('/api/user/profile', ['first_name' => 'Amit'])->assertStatus(401);
    }

    public function test_authenticated_user_can_view_profile(): void
    {
        $response = $this->withToken($this->userToken)
            ->getJson('/api/user/profile')
            ->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.first_name', 'Rahul')
            ->assertJsonPath('data.last_name', 'Sharma')
            ->assertJsonPath('data.full_name', 'Rahul Sharma')
            ->assertJsonPath('data.phone_number', '+919876543210')
            ->assertJsonPath('data.email', 'rahul.sharma@example.com');

        $this->assertIsArray($response->json('data.roles'));
    }

    public function test_user_can_send_email_update_otp(): void
    {
        Notification::fake();

        $response = $this->withToken($this->userToken)
            ->postJson('/api/user/profile/send-email-otp', [
                'email' => 'rahul.new@example.com',
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'OTP has been sent to the email address. Valid for 10 minutes.');

        Notification::assertSentOnDemand(UserEmailUpdateOtpNotification::class);
    }

    public function test_sending_otp_to_same_email_returns_422(): void
    {
        $response = $this->withToken($this->userToken)
            ->postJson('/api/user/profile/send-email-otp', [
                'email' => 'rahul.sharma@example.com',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_sending_otp_to_taken_email_returns_422(): void
    {
        User::create([
            'first_name' => 'Another',
            'last_name' => 'User',
            'name' => 'Another User',
            'phone_number' => '+919876543219',
            'username' => 'another.user',
            'email' => 'taken@example.com',
            'password' => Hash::make('Secret123#'),
            'status' => 'active',
        ]);

        $response = $this->withToken($this->userToken)
            ->postJson('/api/user/profile/send-email-otp', [
                'email' => 'taken@example.com',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_sending_otp_enforces_60_second_cooldown(): void
    {
        Notification::fake();

        $this->withToken($this->userToken)
            ->postJson('/api/user/profile/send-email-otp', [
                'email' => 'cooldown.test@example.com',
            ])
            ->assertStatus(200);

        // Immediate retry -> 429
        $response = $this->withToken($this->userToken)
            ->postJson('/api/user/profile/send-email-otp', [
                'email' => 'cooldown.test@example.com',
            ])
            ->assertStatus(429);

        $this->assertStringContainsString('Please wait', $response->json('message'));
    }

    public function test_user_can_update_name_and_phone_number_without_otp(): void
    {
        $response = $this->withToken($this->userToken)
            ->patchJson('/api/user/profile', [
                'first_name' => 'Rohit',
                'last_name' => 'Verma',
                'phone_number' => '+919988776655',
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.first_name', 'Rohit')
            ->assertJsonPath('data.last_name', 'Verma')
            ->assertJsonPath('data.full_name', 'Rohit Verma')
            ->assertJsonPath('data.phone_number', '+919988776655')
            ->assertJsonPath('data.email', 'rahul.sharma@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'first_name' => 'Rohit',
            'last_name' => 'Verma',
            'name' => 'Rohit Verma',
            'phone_number' => '+919988776655',
        ]);
    }

    public function test_email_update_without_otp_fails_validation(): void
    {
        $response = $this->withToken($this->userToken)
            ->patchJson('/api/user/profile', [
                'email' => 'new.email@example.com',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['otp']);
    }

    public function test_email_update_with_invalid_otp_fails_validation(): void
    {
        $response = $this->withToken($this->userToken)
            ->patchJson('/api/user/profile', [
                'email' => 'new.email@example.com',
                'otp' => '999999',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['otp']);
    }

    public function test_user_can_update_email_with_valid_otp_and_revokes_other_sessions(): void
    {
        $otherToken = $this->user->createToken('user-device-2')->plainTextToken;

        // Generate valid OTP
        $otpService = app(OtpService::class);
        $otpData = $otpService->getOrCreateOtp('verified.new@example.com', 'user_email_update', 10);

        $response = $this->withToken($this->userToken)
            ->patchJson('/api/user/profile', [
                'email' => 'verified.new@example.com',
                'otp' => $otpData['otp'],
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.email', 'verified.new@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'email' => 'verified.new@example.com',
        ]);

        // Current token is still valid
        auth()->forgetGuards();
        $this->withToken($this->userToken)
            ->getJson('/api/user/profile')
            ->assertStatus(200);

        // Other device token was revoked
        auth()->forgetGuards();
        $this->withToken($otherToken)
            ->getJson('/api/user/profile')
            ->assertStatus(401);
    }
}
