<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('admin-login');
        RateLimiter::clear('admin-api');
    }

    public function test_valid_active_admin_can_login_successfully(): void
    {
        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Login successful.',
            ]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertNull($response->json('data.admin'));
        $this->assertNull($response->json('data.token_type'));
    }

    public function test_wrong_password_is_rejected_with_generic_message(): void
    {
        Admin::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('correct_password'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => false,
                'message' => 'Invalid credentials.',
            ]);
    }

    public function test_unknown_email_is_rejected_with_generic_message(): void
    {
        $response = $this->postJson('/api/admin/login', [
            'email' => 'unknown@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => false,
                'message' => 'Invalid credentials.',
            ]);
    }

    public function test_inactive_admin_cannot_login(): void
    {
        Admin::create([
            'name' => 'Inactive Admin',
            'email' => 'inactive@example.com',
            'password' => Hash::make('password123'),
            'status' => 'inactive',
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'inactive@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => false,
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_password_and_sensitive_fields_are_never_exposed_in_login_response(): void
    {
        Admin::create([
            'name' => 'Safe Admin',
            'email' => 'safe@example.com',
            'password' => Hash::make('secret_password_value'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'safe@example.com',
            'password' => 'secret_password_value',
        ]);

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringNotContainsString('secret_password_value', $content);
        $this->assertStringNotContainsString('remember_token', $content);
        $this->assertNull($response->json('data.admin'));
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_last_login_at_is_updated_on_successful_login(): void
    {
        $admin = Admin::create([
            'name' => 'Login Tracker',
            'email' => 'track@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'last_login_at' => null,
        ]);

        $this->assertNull($admin->last_login_at);

        $this->postJson('/api/admin/login', [
            'email' => 'track@example.com',
            'password' => 'password123',
        ])->assertStatus(200);

        $admin->refresh();
        $this->assertNotNull($admin->last_login_at);
    }

    public function test_multi_device_sessions_are_supported(): void
    {
        $admin = Admin::create([
            'name' => 'Multi Device Admin',
            'email' => 'multi@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        // Device 1 login
        $res1 = $this->withHeader('User-Agent', 'Device-Desktop')->postJson('/api/admin/login', [
            'email' => 'multi@example.com',
            'password' => 'password123',
        ]);
        $token1 = $res1->json('data.token');

        // Device 2 login
        $res2 = $this->withHeader('User-Agent', 'Device-Mobile')->postJson('/api/admin/login', [
            'email' => 'multi@example.com',
            'password' => 'password123',
        ]);
        $token2 = $res2->json('data.token');

        $this->assertNotEquals($token1, $token2);
        $this->assertEquals(2, $admin->tokens()->count());

        // Both tokens can make authenticated requests
        auth()->forgetGuards();
        $this->withToken($token1)->postJson('/api/admin/logout')->assertStatus(200);
        $this->assertEquals(1, $admin->fresh()->tokens()->count());

        // Token 2 is still active
        auth()->forgetGuards();
        $this->withToken($token2)->postJson('/api/admin/logout')->assertStatus(200);
        $this->assertEquals(0, $admin->fresh()->tokens()->count());
    }

    public function test_logout_removes_only_current_token_leaving_other_devices_active(): void
    {
        $admin = Admin::create([
            'name' => 'Device Admin',
            'email' => 'device@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $token1 = $admin->createToken('Device 1')->plainTextToken;
        $token2 = $admin->createToken('Device 2')->plainTextToken;

        $this->assertCount(2, $admin->tokens);

        // Logout from Device 1
        auth()->forgetGuards();
        $response = $this->withToken($token1)->postJson('/api/admin/logout');
        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Logged out successfully.',
            ]);

        // Token 1 should no longer work
        auth()->forgetGuards();
        $this->withToken($token1)->postJson('/api/admin/logout')->assertStatus(401);

        // Token 2 must still work
        auth()->forgetGuards();
        $response2 = $this->withToken($token2)->postJson('/api/admin/logout');
        $response2->assertStatus(200);
    }

    public function test_logout_all_removes_all_admin_tokens(): void
    {
        $admin = Admin::create([
            'name' => 'Global Logout Admin',
            'email' => 'global@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $token1 = $admin->createToken('Device 1')->plainTextToken;
        $token2 = $admin->createToken('Device 2')->plainTextToken;
        $token3 = $admin->createToken('Device 3')->plainTextToken;

        $this->assertEquals(3, $admin->tokens()->count());

        // Call logout-all with token1
        auth()->forgetGuards();
        $response = $this->withToken($token1)->postJson('/api/admin/logout-all');
        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Logged out from all devices successfully.',
            ]);

        $this->assertEquals(0, $admin->fresh()->tokens()->count());

        // All tokens fail now
        auth()->forgetGuards();
        $this->withToken($token1)->postJson('/api/admin/logout')->assertStatus(401);
        auth()->forgetGuards();
        $this->withToken($token2)->postJson('/api/admin/logout')->assertStatus(401);
        auth()->forgetGuards();
        $this->withToken($token3)->postJson('/api/admin/logout')->assertStatus(401);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->postJson('/api/admin/logout');
        $response->assertStatus(401)
            ->assertJson([
                'status' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_user_model_token_cannot_access_admin_endpoints(): void
    {
        $user = User::create([
            'name' => 'Regular User',
            'username' => 'reguser',
            'email' => 'user@example.com',
            'password' => Hash::make('userpass'),
            'status' => 'active',
        ]);

        $userToken = $user->createToken('user-token')->plainTextToken;

        $response = $this->withToken($userToken)->postJson('/api/admin/logout');
        $response->assertStatus(403)
            ->assertJson([
                'status' => false,
                'message' => 'Unauthorized access.',
            ]);
    }

    public function test_inactive_admin_with_existing_token_is_blocked_by_middleware(): void
    {
        $admin = Admin::create([
            'name' => 'Soon Inactive',
            'email' => 'soon@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $token = $admin->createToken('admin-token')->plainTextToken;

        // Admin status changed to inactive
        $admin->update(['status' => 'inactive']);

        $response = $this->withToken($token)->postJson('/api/admin/logout');
        $response->assertStatus(403)
            ->assertJson([
                'status' => false,
                'message' => 'Account is inactive. Please contact the administrator.',
            ]);
    }

    public function test_admin_login_rate_limiting_triggers_after_max_attempts(): void
    {
        Admin::create([
            'name' => 'Rate Limit Admin',
            'email' => 'throttle@example.com',
            'password' => Hash::make('correct123'),
            'status' => 'active',
        ]);

        // First 5 attempts fail
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/admin/login', [
                'email' => 'throttle@example.com',
                'password' => 'wrong',
            ])->assertStatus(401);
        }

        // 6th attempt is throttled
        $response = $this->postJson('/api/admin/login', [
            'email' => 'throttle@example.com',
            'password' => 'correct123',
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'status' => false,
                'message' => 'Too many login attempts. Please try again later.',
            ]);
    }

    public function test_different_ip_or_email_has_separate_login_throttle(): void
    {
        Admin::create([
            'name' => 'Admin A',
            'email' => 'admina@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        Admin::create([
            'name' => 'Admin B',
            'email' => 'adminb@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        // Max out admina
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.10'])
                ->postJson('/api/admin/login', [
                    'email' => 'admina@example.com',
                    'password' => 'wrong',
                ]);
        }

        // adminb from same IP is NOT throttled yet because email differs
        $response = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.10'])
            ->postJson('/api/admin/login', [
                'email' => 'adminb@example.com',
                'password' => 'password123',
            ]);

        $response->assertStatus(200);
    }

    public function test_validation_errors_follow_standard_json_format(): void
    {
        $response = $this->postJson('/api/admin/login', []);

        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
                'message' => 'Validation error.',
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'errors' => [
                    'email',
                    'password',
                ],
            ]);
    }

    public function test_admin_api_rate_limiter_is_namespaced_to_admin_id(): void
    {
        $admin1 = Admin::create([
            'name' => 'Admin One',
            'email' => 'admin1@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $admin2 = Admin::create([
            'name' => 'Admin Two',
            'email' => 'admin2@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $request1 = \Illuminate\Http\Request::create('/api/admin/logout', 'POST');
        $request1->setUserResolver(fn () => $admin1);

        $request2 = \Illuminate\Http\Request::create('/api/admin/logout', 'POST');
        $request2->setUserResolver(fn () => $admin2);

        $limiter = RateLimiter::limiter('admin-api');
        $limit1 = $limiter($request1);
        $limit2 = $limiter($request2);

        $this->assertEquals('admin:'.$admin1->id, $limit1->key);
        $this->assertEquals('admin:'.$admin2->id, $limit2->key);
        $this->assertEquals(60, $limit1->maxAttempts);
    }

    public function test_admin_login_when_already_authenticated_reuses_same_token(): void
    {
        $admin = Admin::create([
            'name' => 'Active Admin',
            'email' => 'active_admin@example.com',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        $response1 = $this->postJson('/api/admin/login', [
            'email' => 'active_admin@example.com',
            'password' => 'secret123',
        ]);

        $response1->assertStatus(200);
        $token1 = $response1->json('data.token');

        // Second login within 24 hours returns the EXACT SAME token
        $response2 = $this->postJson('/api/admin/login', [
            'email' => 'active_admin@example.com',
            'password' => 'secret123',
        ]);

        $response2->assertStatus(200);
        $token2 = $response2->json('data.token');

        $this->assertEquals($token1, $token2);
    }

    public function test_admin_token_expires_after_24_hours(): void
    {
        $admin = Admin::create([
            'name' => 'Expiring Admin',
            'email' => 'expiring@example.com',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        $loginResponse = $this->postJson('/api/admin/login', [
            'email' => 'expiring@example.com',
            'password' => 'secret123',
        ]);

        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('data.token');

        // Token works immediately
        $this->withToken($token)->getJson('/api/admin/profile')->assertStatus(200);

        // Travel 25 hours into the future
        $this->travel(25)->hours();

        auth()->forgetGuards();
        $this->withToken($token)->getJson('/api/admin/profile')->assertStatus(401);
    }
}
