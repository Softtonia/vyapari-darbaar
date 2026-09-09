<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Notifications\UserOtpNotification;
use App\Services\OtpService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class UserRegistrationAndOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        RateLimiter::clear('user-send-otp');
        RateLimiter::clear('user-verify-otp');
        RateLimiter::clear('user-register');

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    // ==========================================
    // 1. SEND OTP ENDPOINT TESTS
    // ==========================================

    public function test_send_otp_validates_email_format(): void
    {
        $response = $this->postJson('/api/user/send-otp', [
            'email' => 'invalid-email-address',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_send_otp_rejects_already_registered_email_for_registration(): void
    {
        User::create([
            'first_name' => 'Existing',
            'last_name' => 'User',
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'username' => 'existing.user',
            'password' => Hash::make('Password@123'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/user/send-otp', [
            'email' => 'existing@example.com',
            'purpose' => 'registration',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'An account is already registered with this email address.');
    }

    public function test_send_otp_generates_otp_and_dispatches_notification(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/user/send-otp', [
            'email' => 'newuser@example.com',
            'purpose' => 'registration',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'OTP has been sent to the email address. Valid for 10 minutes.',
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'expires_in_seconds',
                    'cooldown_seconds',
                ],
            ]);

        Notification::assertSentOnDemand(UserOtpNotification::class, function (UserOtpNotification $notification, $channels, $notifiable) {
            return $notifiable->routes['mail'] === 'newuser@example.com'
                && strlen($notification->otp) === 6
                && $notification->purpose === 'registration';
        });
    }

    public function test_send_otp_enforces_60_second_cooldown(): void
    {
        Notification::fake();

        // 1st request
        $this->postJson('/api/user/send-otp', [
            'email' => 'cooldown@example.com',
        ])->assertStatus(200);

        // 2nd request immediately
        $res2 = $this->postJson('/api/user/send-otp', [
            'email' => 'cooldown@example.com',
        ]);

        $res2->assertStatus(429)
            ->assertJson([
                'status' => false,
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'cooldown_remaining_seconds',
                ],
            ]);
    }

    public function test_send_otp_alias_route_works(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/user/otp/send', [
            'email' => 'alias.otp@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true);
    }

    // ==========================================
    // 2. VERIFY OTP ENDPOINT TESTS
    // ==========================================

    public function test_verify_otp_validates_required_fields(): void
    {
        $response = $this->postJson('/api/user/verify-otp', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'otp']);
    }

    public function test_verify_otp_rejects_invalid_otp(): void
    {
        $response = $this->postJson('/api/user/verify-otp', [
            'email' => 'test@example.com',
            'otp' => '999999',
            'purpose' => 'registration',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
                'message' => 'The provided OTP is invalid or has expired.',
            ])
            ->assertJsonValidationErrors(['otp']);
    }

    public function test_verify_otp_succeeds_with_valid_otp(): void
    {
        $otpService = app(OtpService::class);
        $otpData = $otpService->getOrCreateOtp('verify.success@example.com', 'registration');

        $response = $this->postJson('/api/user/verify-otp', [
            'email' => 'verify.success@example.com',
            'otp' => $otpData['otp'],
            'purpose' => 'registration',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'OTP verified successfully.',
                'data' => [
                    'email' => 'verify.success@example.com',
                    'verified' => true,
                ],
            ]);
    }

    // ==========================================
    // 3. REGISTER USER ENDPOINT TESTS
    // ==========================================

    public function test_user_registration_validates_input_fields(): void
    {
        $response = $this->postJson('/api/user/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'email', 'password', 'otp']);
    }

    public function test_user_registration_fails_with_invalid_otp(): void
    {
        $response = $this->postJson('/api/user/register', [
            'first_name' => 'Ramesh',
            'last_name' => 'Kumar',
            'email' => 'ramesh@example.com',
            'phone_number' => '+919876543210',
            'password' => 'Password#2026',
            'password_confirmation' => 'Password#2026',
            'otp' => '000000',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['otp'])
            ->assertJsonPath('errors.otp.0', 'The provided OTP is invalid or has expired.');
    }

    public function test_user_registers_successfully_with_valid_otp_and_receives_sanctum_token(): void
    {
        $otpService = app(OtpService::class);
        $otpData = $otpService->getOrCreateOtp('ramesh.kumar@example.com', 'registration');

        $response = $this->postJson('/api/user/register', [
            'first_name' => 'Ramesh',
            'last_name' => 'Kumar',
            'email' => 'ramesh.kumar@example.com',
            'phone_number' => '+919876543210',
            'password' => 'Password#2026',
            'password_confirmation' => 'Password#2026',
            'otp' => $otpData['otp'],
            'role' => 'trader',
            'device_name' => 'Postman Test Device',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => true,
                'message' => 'User registered successfully.',
                'data' => [
                    'token_type' => 'Bearer',
                    'user' => [
                        'first_name' => 'Ramesh',
                        'last_name' => 'Kumar',
                        'name' => 'Ramesh Kumar',
                        'email' => 'ramesh.kumar@example.com',
                        'phone_number' => '+919876543210',
                        'status' => 'active',
                    ],
                ],
            ]);

        $this->assertNotEmpty($response->json('data.token'));

        // Database assertions
        $this->assertDatabaseHas('users', [
            'email' => 'ramesh.kumar@example.com',
            'first_name' => 'Ramesh',
            'status' => 'active',
            'must_change_password' => false,
        ]);

        $user = User::where('email', 'ramesh.kumar@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('trader'));

        // OTP is consumed and cannot be reused
        $this->assertFalse($otpService->verify('ramesh.kumar@example.com', $otpData['otp'], 'registration'));
    }

    public function test_user_registers_with_default_user_role_if_role_omitted(): void
    {
        $otpService = app(OtpService::class);
        $otpData = $otpService->getOrCreateOtp('default.role@example.com', 'registration');

        $response = $this->postJson('/api/user/register', [
            'first_name' => 'Aakash',
            'email' => 'default.role@example.com',
            'password' => 'Password#2026',
            'password_confirmation' => 'Password#2026',
            'otp' => $otpData['otp'],
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'default.role@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('user'));
    }

    // ==========================================
    // 4. REFRESH TOKEN ENDPOINT TESTS
    // ==========================================

    public function test_refresh_token_requires_authentication(): void
    {
        $this->postJson('/api/user/refresh-token')
            ->assertStatus(401);
    }

    public function test_authenticated_user_can_refresh_token(): void
    {
        $user = User::create([
            'first_name' => 'Pooja',
            'last_name' => 'Hegde',
            'name' => 'Pooja Hegde',
            'email' => 'pooja@example.com',
            'username' => 'pooja.hegde',
            'password' => Hash::make('Password#2026'),
            'status' => 'active',
        ]);
        $user->assignRole('user');

        $initialToken = $user->createToken('test-device')->plainTextToken;

        $response = $this->postJson('/api/user/refresh-token', [], [
            'Authorization' => 'Bearer ' . $initialToken,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Token refreshed successfully.',
                'data' => [
                    'token_type' => 'Bearer',
                ],
            ]);

        $newToken = $response->json('data.token');
        $this->assertNotEmpty($newToken);
        $this->assertNotEquals($initialToken, $newToken);

        // New token works for authenticated profile access
        $profileRes = $this->getJson('/api/user/profile', [
            'Authorization' => 'Bearer ' . $newToken,
            'Accept' => 'application/json',
        ]);

        $profileRes->assertStatus(200)
            ->assertJsonPath('data.email', 'pooja@example.com');
    }
}
