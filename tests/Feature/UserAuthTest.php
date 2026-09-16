<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
        $otpService = app(\App\Services\OtpService::class);
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
        $otpService = app(\App\Services\OtpService::class);
        
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
        $otpService = app(\App\Services\OtpService::class);
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
        $otpService = app(\App\Services\OtpService::class);
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
}
