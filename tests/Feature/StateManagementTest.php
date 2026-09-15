<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\District;
use App\Models\Mandi;
use App\Models\State;
use App\Models\User;
use App\Services\DistrictService;
use App\Services\MandiService;
use App\Services\StateService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StateManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->admin = Admin::create([
            'first_name' => 'State',
            'last_name' => 'Admin',
            'name' => 'State Admin',
            'email' => 'admin.state.crud@example.com',
            'password' => Hash::make('AdminPass@12345'),
            'status' => 'active',
        ]);

        $this->admin->assignRole('admin');
        $this->adminToken = $this->admin->createToken('admin-token')->plainTextToken;

        Cache::forget(StateService::CACHE_KEY_OPTIONS);
    }

    public function test_unauthenticated_requests_are_blocked(): void
    {
        $this->getJson('/api/admin/states')->assertStatus(401);
        $this->getJson('/api/admin/states/options')->assertStatus(401);
        $this->postJson('/api/admin/states', [])->assertStatus(401);
        $this->getJson('/api/admin/states/1')->assertStatus(401);
        $this->putJson('/api/admin/states/1', [])->assertStatus(401);
        $this->patchJson('/api/admin/states/1/status', [])->assertStatus(401);
        $this->patchJson('/api/admin/states/bulk-status', [])->assertStatus(401);
        $this->deleteJson('/api/admin/states/1')->assertStatus(401);
        $this->postJson('/api/admin/states/bulk-delete', [])->assertStatus(401);
    }

    public function test_user_token_is_forbidden_from_admin_states(): void
    {
        $user = User::create([
            'first_name' => 'Regular',
            'last_name' => 'User',
            'name' => 'Regular User',
            'phone_number' => '+919876543210',
            'username' => 'reg.state.user',
            'email' => 'trader.state@example.com',
            'password' => Hash::make('Secret123#'),
            'status' => 'active',
        ]);

        $userToken = $user->createToken('user-token')->plainTextToken;

        $this->withToken($userToken)
            ->getJson('/api/admin/states')
            ->assertStatus(403);
    }

    public function test_admin_can_list_states_with_pagination_and_sorting(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            State::create([
                'name' => "State {$i}",
                'slug' => "state-{$i}",
                'code' => sprintf('ST%02d', $i),
                'sort_order' => $i,
                'status' => true,
            ]);
        }

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/admin/states?per_page=10&page=2')
            ->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'items' => [
                        '*' => ['id', 'name', 'slug', 'code', 'sort_order', 'status', 'created_at', 'updated_at'],
                    ],
                    'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
            ]);

        $this->assertTrue($response->json('status'));
        $this->assertEquals(2, $response->json('data.pagination.current_page'));
        $this->assertEquals(10, count($response->json('data.items')));
        $this->assertEquals(25, $response->json('data.pagination.total'));
    }

    public function test_admin_can_search_states_by_name_and_code(): void
    {
        State::create(['name' => 'Maharashtra', 'slug' => 'maharashtra', 'code' => 'MH', 'status' => true]);
        State::create(['name' => 'Madhya Pradesh', 'slug' => 'madhya-pradesh', 'code' => 'MP', 'status' => true]);
        State::create(['name' => 'Gujarat', 'slug' => 'gujarat', 'code' => 'GJ', 'status' => true]);

        $resName = $this->withToken($this->adminToken)
            ->getJson('/api/admin/states?search=Maha')
            ->assertStatus(200);
        $this->assertCount(1, $resName->json('data.items'));
        $this->assertEquals('MH', $resName->json('data.items.0.code'));

        $resCode = $this->withToken($this->adminToken)
            ->getJson('/api/admin/states?search=mp')
            ->assertStatus(200);
        $this->assertCount(1, $resCode->json('data.items'));
        $this->assertEquals('Madhya Pradesh', $resCode->json('data.items.0.name'));
    }

    public function test_state_code_normalization_trim_and_uppercase(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/states', [
                'name' => 'Maharashtra',
                'code' => '  mh  ',
            ])
            ->assertStatus(201);

        $this->assertEquals('MH', $response->json('data.code'));
        $this->assertDatabaseHas('states', [
            'name' => 'Maharashtra',
            'code' => 'MH',
        ]);
    }

    public function test_admin_can_create_state_with_auto_slug_generation(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/states', [
                'name' => 'Andhra Pradesh',
                'code' => 'AP',
                'sort_order' => 5,
                'status' => true,
            ])
            ->assertStatus(201)
            ->assertJson([
                'status' => true,
                'data' => [
                    'name' => 'Andhra Pradesh',
                    'slug' => 'andhra-pradesh',
                    'code' => 'AP',
                    'sort_order' => 5,
                    'status' => true,
                ],
            ]);

        $this->assertDatabaseHas('states', ['code' => 'AP', 'slug' => 'andhra-pradesh']);
    }

    public function test_slug_uniqueness_handles_collision_including_soft_deleted_records(): void
    {
        $state1 = State::create([
            'name' => 'Karnataka',
            'slug' => 'karnataka',
            'code' => 'KA',
        ]);

        $state1->delete(); // Soft delete

        // Creating state with same slug/name should produce unique slug
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/states', [
                'name' => 'Karnataka',
                'code' => 'KA2',
            ])
            ->assertStatus(201);

        $this->assertEquals('karnataka-2', $response->json('data.slug'));
    }

    public function test_admin_can_retrieve_single_state_with_creator_updater(): void
    {
        $state = State::create([
            'name' => 'Punjab',
            'slug' => 'punjab',
            'code' => 'PB',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->withToken($this->adminToken)
            ->getJson("/api/admin/states/{$state->id}")
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'id' => $state->id,
                    'name' => 'Punjab',
                    'creator' => [
                        'id' => $this->admin->id,
                        'name' => 'State Admin',
                    ],
                ],
            ]);
    }

    public function test_admin_can_update_state(): void
    {
        $state = State::create([
            'name' => 'Orissa',
            'slug' => 'orissa',
            'code' => 'OR',
        ]);

        $this->withToken($this->adminToken)
            ->putJson("/api/admin/states/{$state->id}", [
                'name' => 'Odisha',
                'slug' => 'odisha',
                'code' => ' od ',
            ])
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'name' => 'Odisha',
                    'slug' => 'odisha',
                    'code' => 'OD',
                ],
            ]);

        $this->assertDatabaseHas('states', ['id' => $state->id, 'name' => 'Odisha', 'code' => 'OD', 'slug' => 'odisha']);
    }

    public function test_admin_can_update_single_state_status(): void
    {
        $state = State::create(['name' => 'Goa', 'slug' => 'goa', 'code' => 'GA', 'status' => true]);

        $this->withToken($this->adminToken)
            ->patchJson("/api/admin/states/{$state->id}/status", ['status' => false])
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'status' => false,
                ],
            ]);

        $this->assertDatabaseHas('states', ['id' => $state->id, 'status' => false]);
    }

    public function test_admin_can_bulk_update_state_status(): void
    {
        $s1 = State::create(['name' => 'State 1', 'slug' => 'state-1', 'code' => 'S1', 'status' => true]);
        $s2 = State::create(['name' => 'State 2', 'slug' => 'state-2', 'code' => 'S2', 'status' => true]);

        $this->withToken($this->adminToken)
            ->patchJson('/api/admin/states/bulk-status', [
                'ids' => [$s1->id, $s2->id],
                'status' => false,
            ])
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => ['updated_count' => 2],
            ]);

        $this->assertDatabaseHas('states', ['id' => $s1->id, 'status' => false]);
        $this->assertDatabaseHas('states', ['id' => $s2->id, 'status' => false]);
    }

    public function test_state_deletion_blocked_when_districts_exist_returns_409_state_in_use(): void
    {
        $state = State::create(['name' => 'Maharashtra', 'slug' => 'maharashtra', 'code' => 'MH']);
        District::create(['state_id' => $state->id, 'name' => 'Nagpur', 'slug' => 'nagpur', 'code' => 'NGP']);

        $this->withToken($this->adminToken)
            ->deleteJson("/api/admin/states/{$state->id}")
            ->assertStatus(409)
            ->assertJson([
                'status' => false,
                'error' => 'STATE_IN_USE',
                'message' => 'State cannot be deleted because districts are associated with it.',
            ]);

        $this->assertDatabaseHas('states', ['id' => $state->id, 'deleted_at' => null]);
    }

    public function test_admin_can_soft_delete_state_without_districts(): void
    {
        $state = State::create(['name' => 'Sikkim', 'slug' => 'sikkim', 'code' => 'SK']);

        $this->withToken($this->adminToken)
            ->deleteJson("/api/admin/states/{$state->id}")
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'State deleted successfully.',
            ]);

        $this->assertSoftDeleted('states', ['id' => $state->id]);
    }

    public function test_bulk_delete_states_blocked_if_any_state_has_districts_atomic(): void
    {
        $s1 = State::create(['name' => 'State 1', 'slug' => 'state-1', 'code' => 'S1']);
        $s2 = State::create(['name' => 'State 2', 'slug' => 'state-2', 'code' => 'S2']);
        District::create(['state_id' => $s1->id, 'name' => 'District 1', 'slug' => 'district-1', 'code' => 'D1']);

        $this->withToken($this->adminToken)
            ->postJson('/api/admin/states/bulk-delete', [
                'ids' => [$s1->id, $s2->id],
            ])
            ->assertStatus(409)
            ->assertJson([
                'status' => false,
                'data' => [
                    'blocked_ids' => [$s1->id],
                ],
            ]);

        // S2 should NOT be deleted either (atomic validation before mutation)
        $this->assertDatabaseHas('states', ['id' => $s1->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('states', ['id' => $s2->id, 'deleted_at' => null]);
    }

    public function test_admin_can_bulk_delete_unassigned_states(): void
    {
        $s1 = State::create(['name' => 'State 1', 'slug' => 'state-1', 'code' => 'S1']);
        $s2 = State::create(['name' => 'State 2', 'slug' => 'state-2', 'code' => 'S2']);

        $this->withToken($this->adminToken)
            ->postJson('/api/admin/states/bulk-delete', [
                'ids' => [$s1->id, $s2->id],
            ])
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => ['deleted_count' => 2],
            ]);

        $this->assertSoftDeleted('states', ['id' => $s1->id]);
        $this->assertSoftDeleted('states', ['id' => $s2->id]);
    }

    public function test_state_options_cached_in_redis_and_cleared_on_mutation(): void
    {
        $state = State::create(['name' => 'Gujarat', 'slug' => 'gujarat', 'code' => 'GJ', 'status' => true]);

        // First call caches
        $res1 = $this->withToken($this->adminToken)->getJson('/api/admin/states/options')->assertStatus(200);
        $this->assertCount(1, $res1->json('data'));
        $this->assertTrue(Cache::has(StateService::CACHE_KEY_OPTIONS));

        // Create new state invalidates cache
        $this->withToken($this->adminToken)->postJson('/api/admin/states', [
            'name' => 'Rajasthan',
            'code' => 'RJ',
        ])->assertStatus(201);

        $this->assertFalse(Cache::has(StateService::CACHE_KEY_OPTIONS));

        $res2 = $this->withToken($this->adminToken)->getJson('/api/admin/states/options')->assertStatus(200);
        $this->assertCount(2, $res2->json('data'));
    }

    public function test_state_mutation_invalidates_descendant_district_and_mandi_caches(): void
    {
        $state = State::create(['name' => 'Maharashtra', 'slug' => 'maharashtra', 'code' => 'MH', 'status' => true]);
        $district = District::create(['state_id' => $state->id, 'name' => 'Nagpur', 'slug' => 'nagpur', 'code' => 'NGP', 'status' => true]);
        $mandi = Mandi::create(['district_id' => $district->id, 'name' => 'Nagpur APMC', 'slug' => 'nagpur-apmc', 'code' => 'NGP-APMC', 'status' => true]);

        // Manually seed child caches
        Cache::put(DistrictService::CACHE_KEY_OPTIONS_STATE_PREFIX.$state->id, ['dummy'], 3600);
        Cache::put(MandiService::CACHE_KEY_OPTIONS_STATE_PREFIX.$state->id, ['dummy'], 3600);
        Cache::put(MandiService::CACHE_KEY_OPTIONS_DISTRICT_PREFIX.$district->id, ['dummy'], 3600);

        // Update state status
        $this->withToken($this->adminToken)
            ->patchJson("/api/admin/states/{$state->id}/status", ['status' => false])
            ->assertStatus(200);

        // Verify descendant caches were cleared
        $this->assertFalse(Cache::has(DistrictService::CACHE_KEY_OPTIONS_STATE_PREFIX.$state->id));
        $this->assertFalse(Cache::has(MandiService::CACHE_KEY_OPTIONS_STATE_PREFIX.$state->id));
        $this->assertFalse(Cache::has(MandiService::CACHE_KEY_OPTIONS_DISTRICT_PREFIX.$district->id));
    }
}
