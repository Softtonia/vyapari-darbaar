<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Exceptions\GuardDoesNotMatch;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SpatieRoleAndSchemaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(EmailTemplateSeeder::class);
    }

    /**
     * 1. Existing admin login still works.
     * 4. Admin profile returns first_name + last_name.
     */
    public function test_admin_login_and_profile_returns_first_and_last_name(): void
    {
        $admin = Admin::create([
            'first_name' => 'Rajesh',
            'last_name' => 'Koothrappali',
            'name' => 'Rajesh Koothrappali',
            'email' => 'rajesh@example.com',
            'password' => Hash::make('secretpassword123'),
            'status' => 'active',
        ]);
        $admin->assignRole('admin');

        $loginResponse = $this->postJson('/api/admin/login', [
            'email' => 'rajesh@example.com',
            'password' => 'secretpassword123',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => ['token'],
            ]);

        $token = $loginResponse->json('data.token');

        $profileResponse = $this->withToken($token)->getJson('/api/admin/profile');
        $profileResponse->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'id' => $admin->id,
                    'first_name' => 'Rajesh',
                    'last_name' => 'Koothrappali',
                    'full_name' => 'Rajesh Koothrappali',
                    'email' => 'rajesh@example.com',
                ],
            ]);
    }

    /**
     * 2. Existing user login still works.
     * 3. Existing Sanctum tokens remain functional.
     */
    public function test_user_login_and_sanctum_token_functional(): void
    {
        $user = User::create([
            'first_name' => 'Priya',
            'last_name' => 'Sharma',
            'name' => 'Priya Sharma',
            'phone_number' => '+919876543210',
            'username' => 'priya.sharma',
            'email' => 'priya@example.com',
            'password' => Hash::make('UserSecret123#'),
            'status' => 'active',
        ]);
        $user->assignRole('user');

        $loginResponse = $this->postJson('/api/user/login', [
            'username' => 'priya.sharma',
            'password' => 'UserSecret123#',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'token',
                    'user' => ['id', 'username', 'email'],
                ],
            ]);

        $token = $loginResponse->json('data.token');

        $profileResponse = $this->withToken($token)->getJson('/api/user/profile');
        $profileResponse->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'id' => $user->id,
                    'first_name' => 'Priya',
                    'last_name' => 'Sharma',
                    'phone_number' => '+919876543210',
                    'username' => 'priya.sharma',
                    'email' => 'priya@example.com',
                ],
            ]);
    }

    /**
     * 5. User create accepts first_name/last_name/phone_number.
     * 8. User gets correct default role.
     */
    public function test_user_creation_with_first_name_last_name_phone_number_and_default_role(): void
    {
        Queue::fake();

        $admin = Admin::create([
            'first_name' => 'Admin',
            'last_name' => 'Master',
            'name' => 'Admin Master',
            'email' => 'master@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/admin/users', [
            'first_name' => 'Amit',
            'last_name' => 'Verma',
            'phone_number' => '+919876543210',
            'email' => 'amit.verma@example.com',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => true,
                'data' => [
                    'first_name' => 'Amit',
                    'last_name' => 'Verma',
                    'full_name' => 'Amit Verma',
                    'phone_number' => '+919876543210',
                    'email' => 'amit.verma@example.com',
                    'status' => 'active',
                    'role' => 'user',
                ],
            ]);

        $user = User::where('email', 'amit.verma@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('user'));
    }

    /**
     * 6. User update works.
     * 11. syncRoles works.
     */
    public function test_user_update_and_role_sync(): void
    {
        $admin = Admin::create([
            'first_name' => 'Admin',
            'last_name' => 'Master',
            'name' => 'Admin Master',
            'email' => 'master2@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        $user = User::create([
            'first_name' => 'Vikram',
            'last_name' => 'Batra',
            'name' => 'Vikram Batra',
            'phone_number' => '+919876543210',
            'username' => 'vikram.batra',
            'email' => 'vikram@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $user->assignRole('user');

        $response = $this->withToken($token)->putJson("/api/admin/users/{$user->id}", [
            'first_name' => 'Captain Vikram',
            'last_name' => 'Batra',
            'phone_number' => '+919876543299',
            'email' => 'vikram@example.com',
            'role' => 'guest',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'first_name' => 'Captain Vikram',
                    'last_name' => 'Batra',
                    'full_name' => 'Captain Vikram Batra',
                    'phone_number' => '+919876543299',
                    'email' => 'vikram@example.com',
                    'role' => 'guest',
                ],
            ]);

        $user->refresh();
        $this->assertTrue($user->hasRole('guest'));
        $this->assertFalse($user->hasRole('user'));
    }

    /**
     * 7. Admin create/update supports first_name/last_name.
     */
    public function test_admin_update_supports_first_name_and_last_name(): void
    {
        $admin = Admin::create([
            'first_name' => 'Initial',
            'last_name' => 'Admin',
            'name' => 'Initial Admin',
            'email' => 'init.admin@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        $response = $this->withToken($token)->patchJson('/api/admin/profile', [
            'first_name' => 'UpdatedFirst',
            'last_name' => 'UpdatedLast',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'first_name' => 'UpdatedFirst',
                    'last_name' => 'UpdatedLast',
                    'full_name' => 'UpdatedFirst UpdatedLast',
                ],
            ]);
    }

    /**
     * 9. Admin gets correct admin role.
     * 10. assignRole works.
     * 12. role-based permission inheritance works.
     */
    public function test_admin_role_and_permission_inheritance(): void
    {
        $admin = Admin::create([
            'first_name' => 'Auth',
            'last_name' => 'Admin',
            'name' => 'Auth Admin',
            'email' => 'auth.admin@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $admin->assignRole('admin');

        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue($admin->hasPermissionTo('users.view'));
        $this->assertTrue($admin->hasPermissionTo('roles.create'));
        $this->assertTrue($admin->can('users.view'));
    }

    /**
     * 17. Duplicate role assignment does not create duplicate pivot rows.
     */
    public function test_duplicate_role_assignment_is_idempotent(): void
    {
        $user = User::create([
            'first_name' => 'Single',
            'last_name' => 'RoleUser',
            'name' => 'Single RoleUser',
            'phone_number' => '+919876543210',
            'username' => 'single.role',
            'email' => 'single.role@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $user->assignRole('user');
        $user->assignRole('user');

        $pivotCount = DB::table('model_has_roles')
            ->where('model_id', $user->id)
            ->where('model_type', $user->getMorphClass())
            ->count();

        $this->assertEquals(1, $pivotCount);
    }

    /**
     * 18. Invalid guard role cannot be assigned.
     */
    public function test_invalid_guard_role_cannot_be_assigned(): void
    {
        $admin = Admin::create([
            'first_name' => 'Guard',
            'last_name' => 'Test',
            'name' => 'Guard Test',
            'email' => 'guard.test@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $userRole = Role::where('name', 'user')->where('guard_name', 'web')->firstOrFail();

        // Attempting to assign 'user' role model (which belongs to 'web' guard) to Admin (which has 'admin' guard)
        $this->expectException(GuardDoesNotMatch::class);
        $admin->assignRole($userRole);
    }

    /**
     * 19. No N+1 query on users listing with roles.
     */
    public function test_no_n_plus_one_on_users_with_roles(): void
    {
        $admin = Admin::create([
            'first_name' => 'Query',
            'last_name' => 'Auditor',
            'name' => 'Query Auditor',
            'email' => 'query.auditor@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $token = $admin->createToken('admin-token')->plainTextToken;

        for ($i = 1; $i <= 5; $i++) {
            $u = User::create([
                'first_name' => 'Bench',
                'last_name' => "User {$i}",
                'name' => "Bench User {$i}",
                'phone_number' => "+91987654320{$i}",
                'username' => "bench.user.{$i}",
                'email' => "bench{$i}@example.com",
                'password' => Hash::make('password123'),
                'status' => 'active',
            ]);
            $u->assignRole('user');
        }

        DB::enableQueryLog();

        $response = $this->withToken($token)->getJson('/api/admin/users?per_page=10');
        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        // Eager loaded queries should be bounded (<= 10) instead of growing with each user
        $this->assertLessThanOrEqual(10, count($queries));
    }
}
