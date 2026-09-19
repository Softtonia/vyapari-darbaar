<?php

namespace Tests\Feature;

use App\Enums\EmailTemplateType;
use App\Models\Admin;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Notifications\UserOtpNotification;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class UserAuthTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $plainPassword = 'InitialPassword#123';

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('user-login');
        RateLimiter::clear('user-change-password');
        RateLimiter::clear('user-api');
        RateLimiter::clear('admin-api');

        $this->user = User::create([
            'name' => 'Ajay Kumar',
            'username' => 'ajay.kumar',
            'email' => 'ajay.kumar@example.com',
            'phone_number' => '+919876543210',
            'password' => Hash::make($this->plainPassword),
            'status' => 'active',
            'must_change_password' => true,
        ]);
    }

    public function test_active_user_can_login_with_username_and_password(): void
    {
        $response = $this->postJson('/api/user/login', [
            'username' => 'ajay.kumar',
            'password' => $this->plainPassword,
            'device_name' => 'test-device',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Login successful.',
                'data' => [
                    'token' => $response->json('data.token'),
                ],
            ]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertNull($response->json('data.user'));
        $this->assertNull($response->json('data.token_type'));
    }

    public function test_active_user_can_login_with_username_and_otp(): void
    {
        $otpService = app(OtpService::class);
        $otpData = $otpService->getOrCreateOtp($this->user->email, 'login');

        $response = $this->postJson('/api/user/login', [
            'username' => 'ajay.kumar',
            'otp' => $otpData['otp'],
            'device_name' => 'otp-device',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Login successful.',
            ]);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_user_can_login_with_email_otp_or_number_otp_fields(): void
    {
        $otpService = app(OtpService::class);

        // 1. Using email_otp field
        $otp1 = $otpService->getOrCreateOtp($this->user->email, 'login');
        $res1 = $this->postJson('/api/user/login', [
            'username' => 'ajay.kumar',
            'email_otp' => $otp1['otp'],
        ]);
        $res1->assertStatus(200);

        // 2. Using number_otp field
        $this->user->update(['phone_number' => '+919876543210']);
        $otp2 = $otpService->getOrCreateOtp('+919876543210', 'login');
        $res2 = $this->postJson('/api/user/login', [
            'username' => 'ajay.kumar',
            'number_otp' => $otp2['otp'],
        ]);
        $res2->assertStatus(200);
    }

    public function test_user_can_login_directly_with_email_and_otp(): void
    {
        $otpService = app(OtpService::class);
        $otpData = $otpService->getOrCreateOtp($this->user->email, 'login');

        $response = $this->postJson('/api/auth/user/login', [
            'email' => $this->user->email,
            'otp' => $otpData['otp'],
            'device_name' => 'Web Browser',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Login successful.',
            ]);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_user_can_login_directly_with_phone_number_and_otp(): void
    {
        $this->user->update(['phone_number' => '+919988776655']);
        $otpService = app(OtpService::class);
        $otpData = $otpService->getOrCreateOtp('+919988776655', 'login');

        $response = $this->postJson('/api/auth/user/login', [
            'phone_number' => '+919988776655',
            'otp' => $otpData['otp'],
            'device_name' => 'Android App',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Login successful.',
            ]);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_unified_auth_prefix_endpoints_work_identically(): void
    {
        $response = $this->postJson('/api/auth/user/login', [
            'username' => 'ajay.kumar',
            'password' => $this->plainPassword,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Login successful.',
            ]);

        $token = $response->json('data.token');

        // Refresh token via /api/auth/user/refresh-token
        $refreshRes = $this->withToken($token)->postJson('/api/auth/user/refresh-token');
        $refreshRes->assertStatus(200)->assertJson(['status' => true]);

        $newToken = $refreshRes->json('data.token');

        // Logout via /api/auth/user/logout
        $logoutRes = $this->withToken($newToken)->postJson('/api/auth/user/logout');
        $logoutRes->assertStatus(200)->assertJson(['status' => true]);
    }

    public function test_invalid_otp_returns_error(): void
    {
        $response = $this->postJson('/api/user/login', [
            'username' => 'ajay.kumar',
            'otp' => '999999',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => false,
                'message' => 'The provided OTP is invalid or has expired.',
            ]);
    }

    public function test_wrong_username_and_wrong_password_return_specific_error(): void
    {
        $this->postJson('/api/user/login', [
            'username' => 'nonexistent.user',
            'password' => $this->plainPassword,
        ])->assertStatus(401)->assertJson(['status' => false, 'message' => 'No account found with this username.']);

        $this->postJson('/api/user/login', [
            'username' => 'ajay.kumar',
            'password' => 'WrongPassword#999',
        ])->assertStatus(401)->assertJson(['status' => false, 'message' => 'Incorrect password.']);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->user->update(['status' => 'inactive']);

        $response = $this->postJson('/api/user/login', [
            'username' => 'ajay.kumar',
            'password' => $this->plainPassword,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => false,
                'message' => 'Account is inactive. Please contact administrator.',
            ]);
    }

    public function test_multi_device_login_creates_multiple_valid_tokens(): void
    {
        $res1 = $this->postJson('/api/user/login', [
            'username' => 'ajay.kumar',
            'password' => $this->plainPassword,
            'device_name' => 'Device 1',
        ]);
        $token1 = $res1->json('data.token');

        $res2 = $this->postJson('/api/user/login', [
            'username' => 'ajay.kumar',
            'password' => $this->plainPassword,
            'device_name' => 'Device 2',
        ]);
        $token2 = $res2->json('data.token');

        $this->assertNotEquals($token1, $token2);
        $this->assertEquals(2, $this->user->tokens()->count());

        // Both tokens can access profile
        $this->withToken($token1)->getJson('/api/user/profile')->assertStatus(200);
        auth()->forgetGuards();
        $this->withToken($token2)->getJson('/api/user/profile')->assertStatus(200);
    }

    public function test_user_profile_endpoint_exposes_only_allowed_fields(): void
    {
        $token = $this->user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/user/profile');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'User profile retrieved successfully.',
                'data' => [
                    'id' => $this->user->id,
                    'full_name' => 'Ajay Kumar',
                    'username' => 'ajay.kumar',
                    'email' => 'ajay.kumar@example.com',
                    'status' => 'active',
                    'must_change_password' => true,
                ],
            ]);

        $data = $response->json('data');
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('created_by_admin_id', $data);
        $this->assertArrayNotHasKey('creator', $data);
    }

    public function test_admin_token_cannot_access_user_profile(): void
    {
        $admin = Admin::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('AdminPass#123'),
            'status' => 'active',
        ]);

        $adminToken = $admin->createToken('admin-token')->plainTextToken;

        $this->withToken($adminToken)
            ->getJson('/api/user/profile')
            ->assertStatus(403)
            ->assertJson([
                'status' => false,
                'message' => 'Unauthorized access.',
            ]);
    }

    public function test_user_token_cannot_access_admin_endpoints(): void
    {
        $token = $this->user->createToken('user-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/users')
            ->assertStatus(403)
            ->assertJson([
                'status' => false,
                'message' => 'Unauthorized access.',
            ]);
    }

    public function test_inactive_user_with_existing_token_is_blocked(): void
    {
        $token = $this->user->createToken('active-token')->plainTextToken;

        $this->user->update(['status' => 'inactive']);

        $this->withToken($token)
            ->getJson('/api/user/profile')
            ->assertStatus(403)
            ->assertJson([
                'status' => false,
                'message' => 'Account is inactive. Please contact the administrator.',
            ]);
    }

    public function test_password_change_succeeds_with_strong_password_and_clears_must_change_password(): void
    {
        $tokenA = $this->user->createToken('device-a')->plainTextToken;
        $tokenB = $this->user->createToken('device-b')->plainTextToken;
        $this->assertEquals(2, $this->user->tokens()->count());

        $newPassword = 'NewSecretPassword#2026';

        $response = $this->withToken($tokenA)->postJson('/api/user/change-password', [
            'current_password' => $this->plainPassword,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Password changed successfully.',
            ]);

        $this->user->refresh();
        $this->assertFalse($this->user->must_change_password);
        $this->assertTrue(Hash::check($newPassword, $this->user->password));
        $this->assertFalse(Hash::check($this->plainPassword, $this->user->password));

        // Current token A remains valid
        auth()->forgetGuards();
        $this->withToken($tokenA)->getJson('/api/user/profile')->assertStatus(200);

        // Other token B is revoked
        auth()->forgetGuards();
        $this->withToken($tokenB)->getJson('/api/user/profile')->assertStatus(401);

        // Can log in with new password
        $this->postJson('/api/user/login', [
            'username' => 'ajay.kumar',
            'password' => $newPassword,
        ])->assertStatus(200);

        // Old password fails
        $this->postJson('/api/user/login', [
            'username' => 'ajay.kumar',
            'password' => $this->plainPassword,
        ])->assertStatus(401);
    }

    public function test_password_change_validates_current_password_and_complexity(): void
    {
        $token = $this->user->createToken('test-token')->plainTextToken;

        // Incorrect current password
        $this->withToken($token)->postJson('/api/user/change-password', [
            'current_password' => 'WrongCurrentPassword#123',
            'password' => 'NewStrongPass#2026',
            'password_confirmation' => 'NewStrongPass#2026',
        ])->assertStatus(422)->assertJsonValidationErrors(['current_password']);

        // Weak password (no numbers/symbols)
        $this->withToken($token)->postJson('/api/user/change-password', [
            'current_password' => $this->plainPassword,
            'password' => 'weakpassword',
            'password_confirmation' => 'weakpassword',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);

        // Password confirmation mismatch
        $this->withToken($token)->postJson('/api/user/change-password', [
            'current_password' => $this->plainPassword,
            'password' => 'NewStrongPass#2026',
            'password_confirmation' => 'MismatchPass#2026',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);

        // Same password as current
        $this->withToken($token)->postJson('/api/user/change-password', [
            'current_password' => $this->plainPassword,
            'password' => $this->plainPassword,
            'password_confirmation' => $this->plainPassword,
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_user_logout_revokes_current_token_only(): void
    {
        $token1 = $this->user->createToken('device-1')->plainTextToken;
        $token2 = $this->user->createToken('device-2')->plainTextToken;
        $this->assertEquals(2, $this->user->tokens()->count());

        $response = $this->withToken($token1)->postJson('/api/user/logout');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Logged out successfully.',
            ]);

        // Token 1 is revoked
        auth()->forgetGuards();
        $this->withToken($token1)->getJson('/api/user/profile')->assertStatus(401);

        // Token 2 remains valid
        auth()->forgetGuards();
        $this->withToken($token2)->getJson('/api/user/profile')->assertStatus(200);
    }

    public function test_ensure_password_changed_middleware_gate(): void
    {
        Route::get('/api/test/guarded-route', function () {
            return response()->json(['status' => true, 'message' => 'Access granted.']);
        })->middleware(['auth:sanctum', 'user', 'password.changed']);

        $token = $this->user->createToken('test-token')->plainTextToken;

        // User with must_change_password=true is blocked
        $this->withToken($token)->getJson('/api/test/guarded-route')
            ->assertStatus(403)
            ->assertJson([
                'status' => false,
                'message' => 'Password change required before accessing this resource.',
            ]);

        // After clearing must_change_password, user is allowed
        $this->user->update(['must_change_password' => false]);
        auth()->forgetGuards();

        $this->withToken($token)->getJson('/api/test/guarded-route')
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Access granted.',
            ]);
    }

    public function test_rate_limiters_for_user_endpoints(): void
    {
        $token = $this->user->createToken('rate-limit-test')->plainTextToken;

        // User login throttle: 5 per min
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/user/login', [
                'username' => 'ajay.kumar',
                'password' => 'WrongPass#999',
            ])->assertStatus(401);
        }

        $this->postJson('/api/user/login', [
            'username' => 'ajay.kumar',
            'password' => 'WrongPass#999',
        ])->assertStatus(429);

        // User change password throttle: 5 per min
        for ($i = 1; $i <= 5; $i++) {
            $this->withToken($token)->postJson('/api/user/change-password', [
                'current_password' => 'WrongPass#999',
                'password' => 'ValidNewPass#2026',
                'password_confirmation' => 'ValidNewPass#2026',
            ])->assertStatus(422);
        }

        $this->withToken($token)->postJson('/api/user/change-password', [
            'current_password' => 'WrongPass#999',
            'password' => 'ValidNewPass#2026',
            'password_confirmation' => 'ValidNewPass#2026',
        ])->assertStatus(429);
    }

    public function test_user_relogin_on_same_device_starts_fresh_24_hour_session(): void
    {
        $response1 = $this->postJson('/api/user/login', [
            'username' => 'ajay.kumar',
            'password' => $this->plainPassword,
            'device_name' => 'Device A',
        ]);

        $response1->assertStatus(200);
        $token1 = $response1->json('data.token');

        // Fast forward 12 hours
        $this->travel(12)->hours();

        // Device A logs in again -> previous token replaced with fresh 24-hour token
        $response2 = $this->postJson('/api/user/login', [
            'username' => 'ajay.kumar',
            'password' => $this->plainPassword,
            'device_name' => 'Device A',
        ]);

        $response2->assertStatus(200);
        $token2 = $response2->json('data.token');

        $this->assertNotEquals($token1, $token2);

        // Old token1 is revoked
        auth()->forgetGuards();
        $this->withToken($token1)->getJson('/api/user/profile')->assertStatus(401);

        // New token2 is valid now
        auth()->forgetGuards();
        $this->withToken($token2)->getJson('/api/user/profile')->assertStatus(200);

        // Fast forward another 23 hours (total 35 hours from initial login, but 23 hours from 2nd login)
        $this->travel(23)->hours();
        auth()->forgetGuards();
        $this->withToken($token2)->getJson('/api/user/profile')->assertStatus(200);

        // Fast forward 2 more hours (25 hours from 2nd login) -> expired
        $this->travel(2)->hours();
        auth()->forgetGuards();
        $this->withToken($token2)->getJson('/api/user/profile')->assertStatus(401);
    }

    public function test_multi_device_login_and_logout_lifecycle_with_independent_24h_sessions(): void
    {
        // 1. Login on Device 1
        $res1 = $this->postJson('/api/user/login', [
            'username' => 'ajay.kumar',
            'password' => $this->plainPassword,
            'device_name' => 'Device 1',
        ]);
        $tokenDevice1 = $res1->json('data.token');

        // Travel 10 hours
        $this->travel(10)->hours();

        // 2. Login on Device 2 ("another device") -> gets fresh 24h session
        $res2 = $this->postJson('/api/user/login', [
            'username' => 'ajay.kumar',
            'password' => $this->plainPassword,
            'device_name' => 'Device 2',
        ]);
        $tokenDevice2 = $res2->json('data.token');

        $this->assertNotEquals($tokenDevice1, $tokenDevice2);

        // Both devices are active
        auth()->forgetGuards();
        $this->withToken($tokenDevice1)->getJson('/api/user/profile')->assertStatus(200);
        auth()->forgetGuards();
        $this->withToken($tokenDevice2)->getJson('/api/user/profile')->assertStatus(200);

        // 3. Logout from Device 1
        auth()->forgetGuards();
        $this->withToken($tokenDevice1)->postJson('/api/user/logout')->assertStatus(200);

        // Device 1 token is revoked, Device 2 remains valid
        auth()->forgetGuards();
        $this->withToken($tokenDevice1)->getJson('/api/user/profile')->assertStatus(401);
        auth()->forgetGuards();
        $this->withToken($tokenDevice2)->getJson('/api/user/profile')->assertStatus(200);

        // 4. Re-login on Device 1 -> starts fresh 24h clock again
        $res3 = $this->postJson('/api/user/login', [
            'username' => 'ajay.kumar',
            'password' => $this->plainPassword,
            'device_name' => 'Device 1',
        ]);
        $tokenDevice1New = $res3->json('data.token');

        // Travel 15 hours from Device 2's login (total 25h from initial, 15h for Dev2, 15h after Dev1 re-login)
        $this->travel(15)->hours();
        auth()->forgetGuards();
        $this->withToken($tokenDevice1New)->getJson('/api/user/profile')->assertStatus(200);
        auth()->forgetGuards();
        $this->withToken($tokenDevice2)->getJson('/api/user/profile')->assertStatus(200);

        // Travel 10 more hours (Dev 2 has reached 25h -> expired; Dev 1 new token is at 25h -> expired)
        $this->travel(10)->hours();
        auth()->forgetGuards();
        $this->withToken($tokenDevice1New)->getJson('/api/user/profile')->assertStatus(401);
        auth()->forgetGuards();
        $this->withToken($tokenDevice2)->getJson('/api/user/profile')->assertStatus(401);
    }

    public function test_user_token_expires_after_24_hours(): void
    {
        $loginResponse = $this->postJson('/api/user/login', [
            'username' => 'ajay.kumar',
            'password' => $this->plainPassword,
        ]);

        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('data.token');

        // Token works immediately
        $this->withToken($token)->getJson('/api/user/profile')->assertStatus(200);

        // Travel 25 hours into the future
        $this->travel(25)->hours();

        auth()->forgetGuards();
        $this->withToken($token)->getJson('/api/user/profile')->assertStatus(401);
    }

    public function test_send_login_otp_and_login_with_otp_endpoints_flow(): void
    {
        Notification::fake();

        // 1. Request Login OTP via send-login-otp
        $sendResponse = $this->postJson('/api/user/send-login-otp', [
            'email' => $this->user->email,
        ]);

        $sendResponse->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'OTP has been sent to the email address. Valid for 10 minutes.',
            ]);

        $capturedOtp = null;
        Notification::assertSentOnDemand(
            UserOtpNotification::class,
            function (UserOtpNotification $notification) use (&$capturedOtp) {
                $this->assertEquals('login', $notification->purpose);
                $this->assertNotEmpty($notification->otp);
                $capturedOtp = $notification->otp;

                return true;
            }
        );

        $this->assertNotNull($capturedOtp);

        // 2. Log in using login-with-otp
        $loginResponse = $this->postJson('/api/user/login-with-otp', [
            'email' => $this->user->email,
            'otp' => $capturedOtp,
        ]);

        $loginResponse->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Login successful.',
            ]);

        $this->assertNotEmpty($loginResponse->json('data.token'));
    }

    public function test_mobile_send_login_otp_and_login_with_otp(): void
    {
        Notification::fake();

        // 1. Send Login OTP to 10-digit mobile number (without +91 country code)
        $sendResponse = $this->postJson('/api/user/send-login-otp', [
            'mobile' => '9876543210',
        ]);

        $sendResponse->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'OTP has been sent to the mobile number. Valid for 10 minutes.',
            ]);

        $otp = $sendResponse->json('data.otp');
        $this->assertNotEmpty($otp);

        // 2. Login using the 10-digit mobile and OTP
        $loginResponse = $this->postJson('/api/user/login-with-otp', [
            'mobile' => '9876543210',
            'otp' => $otp,
        ]);

        $loginResponse->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Login successful.',
            ]);

        $this->assertNotEmpty($loginResponse->json('data.token'));
    }

    public function test_root_alias_routes_for_otp_login_with_both_mobile_and_email(): void
    {
        Notification::fake();

        // 1. Email OTP via root alias /api/send-otp
        $sendEmailRes = $this->postJson('/api/send-otp', [
            'email' => $this->user->email,
        ]);
        $sendEmailRes->assertStatus(200)->assertJson(['status' => true]);

        $capturedEmailOtp = null;
        Notification::assertSentOnDemand(
            UserOtpNotification::class,
            function (UserOtpNotification $notification) use (&$capturedEmailOtp) {
                $capturedEmailOtp = $notification->otp;
                return true;
            }
        );
        $this->assertNotNull($capturedEmailOtp);

        // Login via root alias /api/login-with-otp
        $loginEmailRes = $this->postJson('/api/login-with-otp', [
            'email' => $this->user->email,
            'otp' => $capturedEmailOtp,
        ]);
        $loginEmailRes->assertStatus(200)->assertJson(['status' => true]);
        $this->assertNotEmpty($loginEmailRes->json('data.token'));

        // 2. Mobile OTP via root alias /api/send-login-otp
        $sendMobileRes = $this->postJson('/api/send-login-otp', [
            'phone' => '9876543210',
        ]);
        $sendMobileRes->assertStatus(200)->assertJson(['status' => true]);
        $mobileOtp = $sendMobileRes->json('data.otp');
        $this->assertNotEmpty($mobileOtp);

        // Login via root alias /api/login-with-otp using phone
        $loginMobileRes = $this->postJson('/api/login-with-otp', [
            'phone' => '9876543210',
            'otp' => $mobileOtp,
        ]);
        $loginMobileRes->assertStatus(200)->assertJson(['status' => true]);
        $this->assertNotEmpty($loginMobileRes->json('data.token'));
    }

    public function test_user_otp_notification_renders_user_login_otp_email_template(): void
    {
        EmailTemplate::create([
            'name' => 'Custom Login OTP Template',
            'key' => 'USER_LOGIN_OTP',
            'subject' => 'Your Security Code - {{CompanyName}}',
            'body' => '<h1>Hello {{UserName}}</h1><p>Your OTP is {{Otp}}. Expires in {{ExpiryMinutes}} mins.</p>',
            'type' => EmailTemplateType::HTML,
            'is_active' => true,
        ]);

        $notification = new UserOtpNotification('654321', 'login', 'Ajay Kumar');
        $mail = $notification->toMail($this->user);

        $this->assertStringContainsString('Your Security Code', $mail->subject);
        $this->assertNotNull($mail->view);
        $this->assertStringContainsString('654321', (string) $mail->view['html']);
        $this->assertStringContainsString('Ajay Kumar', (string) $mail->view['html']);
        $this->assertStringContainsString('10 mins', (string) $mail->view['html']);
    }
}


