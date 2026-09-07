<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AdminRoleTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('admin-api');

        $this->admin = Admin::create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $this->adminToken = $this->admin->createToken('admin-test-token')->plainTextToken;
    }

    public function test_seeder_creates_default_system_roles_idempotently(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(RoleSeeder::class); // Run second time to test idempotency

        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseHas('roles', ['slug' => 'admin', 'is_system' => true, 'status' => true]);
        $this->assertDatabaseHas('roles', ['slug' => 'user', 'is_system' => true, 'status' => true]);
        $this->assertDatabaseHas('roles', ['slug' => 'guest', 'is_system' => true, 'status' => true]);
    }

    public function test_admin_seeder_assigns_admin_role_via_pivot(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $admin = Admin::where('email', 'admin@vyaparidarbaar.com')->firstOrFail();
        $this->assertTrue($admin->hasRole('admin'));
        $this->assertDatabaseHas('admin_role', [
            'admin_id' => $admin->id,
        ]);
    }

    public function test_user_and_admin_role_helpers_and_pivot_relationships(): void
    {
        $this->seed(RoleSeeder::class);
        $adminRole = Role::where('slug', 'admin')->firstOrFail();
        $userRole = Role::where('slug', 'user')->firstOrFail();

        // Admin role assignment & relationships
        $this->admin->assignRole('admin');
        $this->assertTrue($this->admin->hasRole('admin'));
        $this->assertFalse($this->admin->hasRole('user'));
        $this->assertTrue($adminRole->admins->contains('id', $this->admin->id));

        // User role assignment & relationships
        $user = User::create([
            'name' => 'Demo User',
            'username' => 'demo.user',
            'email' => 'demo@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $user->assignRole('user');
        $this->assertTrue($user->hasRole('user'));
        $this->assertFalse($user->hasRole('admin'));
        $this->assertTrue($userRole->users->contains('id', $user->id));
    }

    public function test_admin_can_create_custom_role_with_explicit_slug(): void
    {
        $response = $this->withToken($this->adminToken)->postJson('/api/admin/roles', [
            'name' => 'Content Editor',
            'slug' => 'content-editor',
            'status' => true,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => true,
                'message' => 'Role created successfully.',
                'data' => [
                    'name' => 'Content Editor',
                    'slug' => 'content-editor',
                    'status' => true,
                    'is_system' => false,
                ],
            ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'Content Editor',
            'slug' => 'content-editor',
            'is_system' => false,
        ]);
    }

    public function test_admin_can_create_role_with_auto_generated_slug(): void
    {
        $response = $this->withToken($this->adminToken)->postJson('/api/admin/roles', [
            'name' => 'Marketing Manager',
            'status' => true,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => true,
                'data' => [
                    'name' => 'Marketing Manager',
                    'slug' => 'marketing-manager',
                ],
            ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'Marketing Manager',
            'slug' => 'marketing-manager',
        ]);
    }

    public function test_duplicate_slug_is_rejected_with_validation_error(): void
    {
        Role::create([
            'name' => 'Finance',
            'slug' => 'finance',
            'status' => true,
            'is_system' => false,
        ]);

        $response = $this->withToken($this->adminToken)->postJson('/api/admin/roles', [
            'name' => 'Finance Department',
            'slug' => 'finance',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
                'message' => 'Validation error.',
            ])
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_admin_can_list_roles_with_pagination_and_default_sorting(): void
    {
        $this->seed(RoleSeeder::class);

        $response = $this->withToken($this->adminToken)->getJson('/api/admin/roles?per_page=10');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Roles retrieved successfully.',
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'current_page',
                    'data' => [
                        '*' => ['id', 'name', 'slug', 'status', 'is_system', 'created_at', 'updated_at'],
                    ],
                    'per_page',
                    'total',
                ],
            ]);

        $this->assertEquals(3, $response->json('data.total'));
    }

    public function test_admin_can_search_and_filter_roles(): void
    {
        $this->seed(RoleSeeder::class);

        Role::create(['name' => 'Auditor', 'slug' => 'auditor', 'status' => false, 'is_system' => false]);
        Role::create(['name' => 'Compliance Lead', 'slug' => 'compliance-lead', 'status' => true, 'is_system' => false]);

        // Search by name
        $res1 = $this->withToken($this->adminToken)->getJson('/api/admin/roles?search=Audit');
        $this->assertCount(1, $res1->json('data.data'));
        $this->assertEquals('auditor', $res1->json('data.data.0.slug'));

        // Filter by status (inactive)
        $res2 = $this->withToken($this->adminToken)->getJson('/api/admin/roles?status=0');
        $this->assertCount(1, $res2->json('data.data'));
        $this->assertEquals('auditor', $res2->json('data.data.0.slug'));

        // Filter by is_system
        $res3 = $this->withToken($this->adminToken)->getJson('/api/admin/roles?is_system=1');
        $this->assertCount(3, $res3->json('data.data'));

        // Sorting by name asc
        $res4 = $this->withToken($this->adminToken)->getJson('/api/admin/roles?sort_by=name&sort_order=asc');
        $names = array_column($res4->json('data.data'), 'name');
        $sortedNames = $names;
        sort($sortedNames);
        $this->assertEquals($sortedNames, $names);
    }

    public function test_admin_can_show_single_role(): void
    {
        $role = Role::create([
            'name' => 'Viewer',
            'slug' => 'viewer',
            'status' => true,
            'is_system' => false,
        ]);

        $response = $this->withToken($this->adminToken)->getJson("/api/admin/roles/{$role->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'id' => $role->id,
                    'name' => 'Viewer',
                    'slug' => 'viewer',
                ],
            ]);
    }

    public function test_admin_can_update_custom_role(): void
    {
        $role = Role::create([
            'name' => 'Editor',
            'slug' => 'editor',
            'status' => true,
            'is_system' => false,
        ]);

        $response = $this->withToken($this->adminToken)->putJson("/api/admin/roles/{$role->id}", [
            'name' => 'Senior Editor',
            'slug' => 'senior-editor',
            'status' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'name' => 'Senior Editor',
                    'slug' => 'senior-editor',
                    'status' => false,
                ],
            ]);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'Senior Editor',
            'slug' => 'senior-editor',
            'status' => false,
        ]);
    }

    public function test_system_role_slug_is_protected_against_accidental_mutation_on_update(): void
    {
        $this->seed(RoleSeeder::class);
        $adminRole = Role::where('slug', 'admin')->firstOrFail();

        $response = $this->withToken($this->adminToken)->putJson("/api/admin/roles/{$adminRole->id}", [
            'name' => 'Super Administrator',
            'slug' => 'super-admin-renamed',
            'status' => true,
        ]);

        $response->assertStatus(200);

        // Name was updated, but slug was safely preserved as 'admin'
        $adminRole->refresh();
        $this->assertEquals('Super Administrator', $adminRole->name);
        $this->assertEquals('admin', $adminRole->slug);
    }

    public function test_admin_can_update_role_status_via_patch(): void
    {
        $role = Role::create([
            'name' => 'Staff',
            'slug' => 'staff',
            'status' => true,
            'is_system' => false,
        ]);

        $response = $this->withToken($this->adminToken)->patchJson("/api/admin/roles/{$role->id}/status", [
            'status' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'id' => $role->id,
                    'status' => false,
                ],
            ]);

        $this->assertFalse($role->fresh()->status);
    }

    public function test_admin_can_soft_delete_custom_role(): void
    {
        $role = Role::create([
            'name' => 'Temporary',
            'slug' => 'temporary',
            'status' => true,
            'is_system' => false,
        ]);

        $response = $this->withToken($this->adminToken)->deleteJson("/api/admin/roles/{$role->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Role deleted successfully.',
            ]);

        $this->assertSoftDeleted('roles', ['id' => $role->id]);
    }

    public function test_system_role_cannot_be_deleted(): void
    {
        $this->seed(RoleSeeder::class);
        $adminRole = Role::where('slug', 'admin')->firstOrFail();

        $response = $this->withToken($this->adminToken)->deleteJson("/api/admin/roles/{$adminRole->id}");

        $response->assertStatus(403)
            ->assertJson([
                'status' => false,
                'message' => 'System role cannot be deleted.',
            ]);

        $this->assertDatabaseHas('roles', ['id' => $adminRole->id, 'deleted_at' => null]);
    }

    public function test_admin_can_bulk_delete_custom_roles(): void
    {
        $role1 = Role::create(['name' => 'R1', 'slug' => 'r1', 'status' => true, 'is_system' => false]);
        $role2 = Role::create(['name' => 'R2', 'slug' => 'r2', 'status' => true, 'is_system' => false]);
        $role3 = Role::create(['name' => 'R3', 'slug' => 'r3', 'status' => true, 'is_system' => false]);

        $response = $this->withToken($this->adminToken)->deleteJson('/api/admin/roles/bulk-delete', [
            'ids' => [$role1->id, $role2->id, $role3->id],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Roles deleted successfully.',
                'data' => [
                    'deleted_count' => 3,
                ],
            ]);

        $this->assertSoftDeleted('roles', ['id' => $role1->id]);
        $this->assertSoftDeleted('roles', ['id' => $role2->id]);
        $this->assertSoftDeleted('roles', ['id' => $role3->id]);
    }

    public function test_bulk_delete_rejects_and_prevents_partial_deletion_if_system_role_is_present(): void
    {
        $this->seed(RoleSeeder::class);
        $adminRole = Role::where('slug', 'admin')->firstOrFail();
        $customRole = Role::create(['name' => 'Custom', 'slug' => 'custom', 'status' => true, 'is_system' => false]);

        $response = $this->withToken($this->adminToken)->deleteJson('/api/admin/roles/bulk-delete', [
            'ids' => [$customRole->id, $adminRole->id],
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => false,
                'message' => 'System roles cannot be deleted.',
            ]);

        // Atomic check: Ensure NEITHER role was deleted
        $this->assertDatabaseHas('roles', ['id' => $adminRole->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('roles', ['id' => $customRole->id, 'deleted_at' => null]);
    }

    public function test_bulk_delete_validates_duplicate_and_non_existent_ids(): void
    {
        $response1 = $this->withToken($this->adminToken)->deleteJson('/api/admin/roles/bulk-delete', [
            'ids' => [1, 1],
        ]);
        $response1->assertStatus(422)->assertJsonValidationErrors(['ids.0', 'ids.1']);

        $response2 = $this->withToken($this->adminToken)->deleteJson('/api/admin/roles/bulk-delete', [
            'ids' => [999999],
        ]);
        $response2->assertStatus(422)->assertJsonValidationErrors(['ids.0']);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/admin/roles')->assertStatus(401);
        $this->postJson('/api/admin/roles', ['name' => 'Test'])->assertStatus(401);
    }

    public function test_user_token_cannot_access_admin_role_endpoints(): void
    {
        $user = User::create([
            'name' => 'App User',
            'username' => 'app.user',
            'email' => 'app.user@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $userToken = $user->createToken('user-token')->plainTextToken;

        $this->withToken($userToken)->getJson('/api/admin/roles')->assertStatus(403);
    }
}
