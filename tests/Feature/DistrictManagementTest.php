<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\District;
use App\Models\Mandi;
use App\Models\State;
use App\Models\User;
use App\Services\DistrictService;
use App\Services\MandiService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DistrictManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected string $adminToken;

    protected State $stateMH;

    protected State $stateMP;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->admin = Admin::create([
            'first_name' => 'District',
            'last_name' => 'Admin',
            'name' => 'District Admin',
            'email' => 'admin.district.crud@example.com',
            'password' => Hash::make('AdminPass@12345'),
            'status' => 'active',
        ]);

        $this->admin->assignRole('admin');
        $this->adminToken = $this->admin->createToken('admin-token')->plainTextToken;

        $this->stateMH = State::create(['name' => 'Maharashtra', 'slug' => 'maharashtra', 'code' => 'MH', 'status' => true]);
        $this->stateMP = State::create(['name' => 'Madhya Pradesh', 'slug' => 'madhya-pradesh', 'code' => 'MP', 'status' => true]);

        Cache::forget(DistrictService::CACHE_KEY_OPTIONS_STATE_PREFIX.$this->stateMH->id);
        Cache::forget(DistrictService::CACHE_KEY_OPTIONS_STATE_PREFIX.$this->stateMP->id);
    }

    public function test_unauthenticated_requests_are_blocked(): void
    {
        $this->getJson('/api/admin/districts')->assertStatus(401);
        $this->getJson('/api/admin/districts/options')->assertStatus(401);
        $this->postJson('/api/admin/districts', [])->assertStatus(401);
        $this->getJson('/api/admin/districts/1')->assertStatus(401);
        $this->putJson('/api/admin/districts/1', [])->assertStatus(401);
        $this->patchJson('/api/admin/districts/1/status', [])->assertStatus(401);
        $this->patchJson('/api/admin/districts/bulk-status', [])->assertStatus(401);
        $this->deleteJson('/api/admin/districts/1')->assertStatus(401);
        $this->postJson('/api/admin/districts/bulk-delete', [])->assertStatus(401);
    }

    public function test_user_token_is_forbidden_from_admin_districts(): void
    {
        $user = User::create([
            'first_name' => 'Regular',
            'last_name' => 'User',
            'name' => 'Regular User',
            'phone_number' => '+919876543210',
            'username' => 'reg.district.user',
            'email' => 'trader.district@example.com',
            'password' => Hash::make('Secret123#'),
            'status' => 'active',
        ]);

        $userToken = $user->createToken('user-token')->plainTextToken;

        $this->withToken($userToken)
            ->getJson('/api/admin/districts')
            ->assertStatus(403);
    }

    public function test_admin_can_list_districts_with_eager_loaded_state_and_pagination(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            District::create([
                'state_id' => $this->stateMH->id,
                'name' => "District {$i}",
                'slug' => "district-{$i}",
                'code' => sprintf('D%02d', $i),
                'sort_order' => $i,
                'status' => true,
            ]);
        }

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/admin/districts?per_page=10&page=1')
            ->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'items' => [
                        '*' => [
                            'id',
                            'state_id',
                            'state' => ['id', 'name', 'slug', 'code'],
                            'name',
                            'slug',
                            'code',
                            'sort_order',
                            'status',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
            ]);

        $this->assertEquals(10, count($response->json('data.items')));
        $this->assertEquals('Maharashtra', $response->json('data.items.0.state.name'));
    }

    public function test_admin_can_filter_districts_by_state(): void
    {
        District::create(['state_id' => $this->stateMH->id, 'name' => 'Nagpur', 'slug' => 'nagpur', 'code' => 'NGP', 'status' => true]);
        District::create(['state_id' => $this->stateMP->id, 'name' => 'Indore', 'slug' => 'indore', 'code' => 'IND', 'status' => true]);

        $res = $this->withToken($this->adminToken)
            ->getJson("/api/admin/districts?state_id={$this->stateMP->id}")
            ->assertStatus(200);

        $this->assertCount(1, $res->json('data.items'));
        $this->assertEquals('Indore', $res->json('data.items.0.name'));
    }

    public function test_admin_can_search_districts_by_name_and_code(): void
    {
        District::create(['state_id' => $this->stateMH->id, 'name' => 'Nagpur', 'slug' => 'nagpur', 'code' => 'NGP', 'status' => true]);
        District::create(['state_id' => $this->stateMH->id, 'name' => 'Nashik', 'slug' => 'nashik', 'code' => 'NSK', 'status' => true]);

        $res = $this->withToken($this->adminToken)
            ->getJson('/api/admin/districts?search=nag')
            ->assertStatus(200);
        $this->assertCount(1, $res->json('data.items'));
        $this->assertEquals('Nagpur', $res->json('data.items.0.name'));

        $resCode = $this->withToken($this->adminToken)
            ->getJson('/api/admin/districts?search=nsk')
            ->assertStatus(200);
        $this->assertCount(1, $resCode->json('data.items'));
        $this->assertEquals('Nashik', $resCode->json('data.items.0.name'));
    }

    public function test_district_creation_requires_valid_active_state(): void
    {
        $inactiveState = State::create(['name' => 'Inactive State', 'slug' => 'inactive-state', 'code' => 'IS', 'status' => false]);

        $this->withToken($this->adminToken)
            ->postJson('/api/admin/districts', [
                'state_id' => $inactiveState->id,
                'name' => 'Test District',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['state_id']);
    }

    public function test_district_code_normalization(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/districts', [
                'state_id' => $this->stateMH->id,
                'name' => 'Nagpur',
                'code' => '  ngp  ',
            ])
            ->assertStatus(201);

        $this->assertEquals('NGP', $response->json('data.code'));
    }

    public function test_admin_can_create_district_with_state_scoped_slug(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/districts', [
                'state_id' => $this->stateMH->id,
                'name' => 'Akola',
                'code' => 'AKL',
                'sort_order' => 2,
            ])
            ->assertStatus(201)
            ->assertJson([
                'status' => true,
                'data' => [
                    'name' => 'Akola',
                    'slug' => 'akola',
                    'code' => 'AKL',
                    'state_id' => $this->stateMH->id,
                ],
            ]);

        $this->assertDatabaseHas('districts', [
            'state_id' => $this->stateMH->id,
            'slug' => 'akola',
        ]);
    }

    public function test_district_slug_uniqueness_within_same_state_handles_collision_including_trashed(): void
    {
        $d1 = District::create([
            'state_id' => $this->stateMH->id,
            'name' => 'Latur',
            'slug' => 'latur',
            'code' => 'LTR1',
        ]);
        $d1->delete(); // soft deleted

        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/districts', [
                'state_id' => $this->stateMH->id,
                'name' => 'Latur',
                'code' => 'LTR2',
            ])
            ->assertStatus(201);

        $this->assertEquals('latur-2', $response->json('data.slug'));
    }

    public function test_same_district_slug_allowed_in_different_states(): void
    {
        // Aurangabad exists in MH (now Chhatrapati Sambhajinagar) and Bihar
        District::create([
            'state_id' => $this->stateMH->id,
            'name' => 'Aurangabad',
            'slug' => 'aurangabad',
            'code' => 'AUR-MH',
        ]);

        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/districts', [
                'state_id' => $this->stateMP->id,
                'name' => 'Aurangabad',
                'code' => 'AUR-MP',
            ])
            ->assertStatus(201);

        $this->assertEquals('aurangabad', $response->json('data.slug'));
    }

    public function test_admin_can_retrieve_single_district_with_state_creator_updater(): void
    {
        $district = District::create([
            'state_id' => $this->stateMH->id,
            'name' => 'Kolhapur',
            'slug' => 'kolhapur',
            'code' => 'KLP',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->withToken($this->adminToken)
            ->getJson("/api/admin/districts/{$district->id}")
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'id' => $district->id,
                    'name' => 'Kolhapur',
                    'state' => [
                        'id' => $this->stateMH->id,
                        'name' => 'Maharashtra',
                    ],
                    'creator' => [
                        'id' => $this->admin->id,
                    ],
                ],
            ]);
    }

    public function test_admin_can_update_district_and_reassign_parent_state(): void
    {
        $district = District::create([
            'state_id' => $this->stateMH->id,
            'name' => 'Border District',
            'slug' => 'border-district',
            'code' => 'BD',
        ]);

        $this->withToken($this->adminToken)
            ->putJson("/api/admin/districts/{$district->id}", [
                'state_id' => $this->stateMP->id,
                'name' => 'Border District MP',
                'code' => 'bd-mp',
            ])
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'state_id' => $this->stateMP->id,
                    'name' => 'Border District MP',
                    'code' => 'BD-MP',
                ],
            ]);

        $this->assertDatabaseHas('districts', [
            'id' => $district->id,
            'state_id' => $this->stateMP->id,
            'name' => 'Border District MP',
            'code' => 'BD-MP',
        ]);
    }

    public function test_district_parent_reassignment_invalidates_both_old_and_new_state_caches(): void
    {
        $district = District::create([
            'state_id' => $this->stateMH->id,
            'name' => 'Test District',
            'slug' => 'test-district',
            'code' => 'TD',
            'status' => true,
        ]);

        Cache::put(DistrictService::CACHE_KEY_OPTIONS_STATE_PREFIX.$this->stateMH->id, ['dummyMH'], 3600);
        Cache::put(DistrictService::CACHE_KEY_OPTIONS_STATE_PREFIX.$this->stateMP->id, ['dummyMP'], 3600);

        $this->withToken($this->adminToken)
            ->putJson("/api/admin/districts/{$district->id}", [
                'state_id' => $this->stateMP->id,
                'name' => 'Test District Moved',
            ])
            ->assertStatus(200);

        $this->assertFalse(Cache::has(DistrictService::CACHE_KEY_OPTIONS_STATE_PREFIX.$this->stateMH->id));
        $this->assertFalse(Cache::has(DistrictService::CACHE_KEY_OPTIONS_STATE_PREFIX.$this->stateMP->id));
    }

    public function test_admin_can_update_single_district_status(): void
    {
        $district = District::create([
            'state_id' => $this->stateMH->id,
            'name' => 'Pune',
            'slug' => 'pune',
            'code' => 'PUN',
            'status' => true,
        ]);

        $this->withToken($this->adminToken)
            ->patchJson("/api/admin/districts/{$district->id}/status", ['status' => false])
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => ['status' => false],
            ]);

        $this->assertDatabaseHas('districts', ['id' => $district->id, 'status' => false]);
    }

    public function test_admin_can_bulk_update_district_status(): void
    {
        $d1 = District::create(['state_id' => $this->stateMH->id, 'name' => 'D1', 'slug' => 'd1', 'status' => true]);
        $d2 = District::create(['state_id' => $this->stateMH->id, 'name' => 'D2', 'slug' => 'd2', 'status' => true]);

        $this->withToken($this->adminToken)
            ->patchJson('/api/admin/districts/bulk-status', [
                'ids' => [$d1->id, $d2->id],
                'status' => false,
            ])
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => ['updated_count' => 2],
            ]);

        $this->assertDatabaseHas('districts', ['id' => $d1->id, 'status' => false]);
        $this->assertDatabaseHas('districts', ['id' => $d2->id, 'status' => false]);
    }

    public function test_district_deletion_blocked_when_mandis_exist_returns_409_district_in_use(): void
    {
        $district = District::create(['state_id' => $this->stateMH->id, 'name' => 'Nagpur', 'slug' => 'nagpur', 'code' => 'NGP']);
        Mandi::create(['district_id' => $district->id, 'name' => 'Nagpur APMC', 'slug' => 'nagpur-apmc', 'code' => 'NGP-APMC']);

        $this->withToken($this->adminToken)
            ->deleteJson("/api/admin/districts/{$district->id}")
            ->assertStatus(409)
            ->assertJson([
                'status' => false,
                'error' => 'DISTRICT_IN_USE',
                'message' => 'District cannot be deleted because mandis are associated with it.',
            ]);

        $this->assertDatabaseHas('districts', ['id' => $district->id, 'deleted_at' => null]);
    }

    public function test_admin_can_soft_delete_district_without_mandis(): void
    {
        $district = District::create(['state_id' => $this->stateMH->id, 'name' => 'Solapur', 'slug' => 'solapur', 'code' => 'SLP']);

        $this->withToken($this->adminToken)
            ->deleteJson("/api/admin/districts/{$district->id}")
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'District deleted successfully.',
            ]);

        $this->assertSoftDeleted('districts', ['id' => $district->id]);
    }

    public function test_bulk_delete_districts_blocked_if_any_district_has_mandis_atomic(): void
    {
        $d1 = District::create(['state_id' => $this->stateMH->id, 'name' => 'D1', 'slug' => 'd1']);
        $d2 = District::create(['state_id' => $this->stateMH->id, 'name' => 'D2', 'slug' => 'd2']);
        Mandi::create(['district_id' => $d1->id, 'name' => 'Mandi 1', 'slug' => 'mandi-1', 'code' => 'M1']);

        $this->withToken($this->adminToken)
            ->postJson('/api/admin/districts/bulk-delete', [
                'ids' => [$d1->id, $d2->id],
            ])
            ->assertStatus(409)
            ->assertJson([
                'status' => false,
                'data' => [
                    'blocked_ids' => [$d1->id],
                ],
            ]);

        $this->assertDatabaseHas('districts', ['id' => $d1->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('districts', ['id' => $d2->id, 'deleted_at' => null]);
    }

    public function test_admin_can_bulk_delete_unassigned_districts(): void
    {
        $d1 = District::create(['state_id' => $this->stateMH->id, 'name' => 'D1', 'slug' => 'd1']);
        $d2 = District::create(['state_id' => $this->stateMH->id, 'name' => 'D2', 'slug' => 'd2']);

        $this->withToken($this->adminToken)
            ->postJson('/api/admin/districts/bulk-delete', [
                'ids' => [$d1->id, $d2->id],
            ])
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => ['deleted_count' => 2],
            ]);

        $this->assertSoftDeleted('districts', ['id' => $d1->id]);
        $this->assertSoftDeleted('districts', ['id' => $d2->id]);
    }

    public function test_district_options_cached_by_state(): void
    {
        District::create(['state_id' => $this->stateMH->id, 'name' => 'Nagpur', 'slug' => 'nagpur', 'code' => 'NGP', 'status' => true]);

        $res1 = $this->withToken($this->adminToken)
            ->getJson("/api/admin/districts/options?state_id={$this->stateMH->id}")
            ->assertStatus(200);

        $this->assertCount(1, $res1->json('data'));
        $this->assertTrue(Cache::has(DistrictService::CACHE_KEY_OPTIONS_STATE_PREFIX.$this->stateMH->id));
    }
}
