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
                    'token_type' => 'Bearer',
                    'user' => [
                        'id' => $this->user->id,
                        'name' => 'Ajay Kumar',
                        'username' => 'ajay.kumar',
                        'email' => 'ajay.kumar@example.com',
                        'status' => 'active',
                        'must_change_password' => true,
                    ],
                ],
            ]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertArrayNotHasKey('password', $response->json('data.user'));
    }

    public function test_email_cannot_be_used_as_login_identifier(): void
    {
        $response = $this->postJson('/api/user/login', [
            'username' => 'ajay.kumar@example.com',
            'password' => $this->plainPassword,
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => false,
                'message' => 'No account found with this username.',
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
                    'name' => 'Ajay Kumar',
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

    public function test_user_login_when_already_authenticated_reuses_same_token(): void
    {
        $response1 = $this->postJson('/api/user/login', [
            'username' => 'ajay.kumar',
            'password' => $this->plainPassword,
        ]);

        $response1->assertStatus(200);
        $token1 = $response1->json('data.token');

        // Second login within 24 hours returns the EXACT SAME token
        $response2 = $this->postJson('/api/user/login', [
            'username' => 'ajay.kumar',
            'password' => $this->plainPassword,
        ]);

        $response2->assertStatus(200);
        $token2 = $response2->json('data.token');

        $this->assertEquals($token1, $token2);
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
