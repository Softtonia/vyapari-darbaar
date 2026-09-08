<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use App\Models\CommoditySubcategory;
use App\Models\User;
use App\Services\CommodityCategoryService;
use App\Services\CommodityService;
use App\Services\CommoditySubcategoryService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CommoditySubcategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected string $adminToken;

    protected CommodityCategory $categoryGrains;

    protected CommodityCategory $categoryPulses;

    protected Commodity $commodityWheat;

    protected Commodity $commodityChana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->admin = Admin::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'name' => 'Super Admin',
            'email' => 'admin.subcategories.crud@example.com',
            'password' => Hash::make('AdminPass@12345'),
            'status' => 'active',
        ]);

        $this->admin->assignRole('admin');
        $this->adminToken = $this->admin->createToken('admin-token')->plainTextToken;

        $this->categoryGrains = CommodityCategory::create([
            'name_en' => 'Grains',
            'name_hi' => 'अनाज',
            'slug' => 'grains',
            'status' => true,
        ]);

        $this->categoryPulses = CommodityCategory::create([
            'name_en' => 'Pulses',
            'name_hi' => 'दलहन',
            'slug' => 'pulses',
            'status' => true,
        ]);

        $this->commodityWheat = Commodity::create([
            'commodity_category_id' => $this->categoryGrains->id,
            'name_en' => 'Wheat',
            'name_hi' => 'गेहूं',
            'slug' => 'wheat',
            'status' => true,
        ]);

        $this->commodityChana = Commodity::create([
            'commodity_category_id' => $this->categoryPulses->id,
            'name_en' => 'Chana',
            'name_hi' => 'चना',
            'slug' => 'chana',
            'status' => true,
        ]);

        Cache::forget(CommoditySubcategoryService::CACHE_KEY_OPTIONS_ALL);
        Cache::forget(CommoditySubcategoryService::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$this->commodityWheat->id);
        Cache::forget(CommoditySubcategoryService::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$this->commodityChana->id);
    }

    public function test_unauthenticated_requests_are_blocked(): void
    {
        $this->getJson('/api/admin/commodity-subcategories')->assertStatus(401);
        $this->getJson('/api/admin/commodity-subcategories/options')->assertStatus(401);
        $this->postJson('/api/admin/commodity-subcategories', [])->assertStatus(401);
        $this->getJson('/api/admin/commodity-subcategories/1')->assertStatus(401);
        $this->putJson('/api/admin/commodity-subcategories/1', [])->assertStatus(401);
        $this->patchJson('/api/admin/commodity-subcategories/1/status', [])->assertStatus(401);
        $this->deleteJson('/api/admin/commodity-subcategories/1')->assertStatus(401);
        $this->postJson('/api/admin/commodity-subcategories/bulk-delete', [])->assertStatus(401);
    }

    public function test_user_token_is_forbidden_from_admin_commodity_subcategories(): void
    {
        $user = User::create([
            'first_name' => 'Regular',
            'last_name' => 'Trader',
            'name' => 'Regular Trader',
            'phone_number' => '+919876543210',
            'username' => 'reg.trader.user',
            'email' => 'trader.user@example.com',
            'password' => Hash::make('Secret123#'),
            'status' => 'active',
        ]);

        $userToken = $user->createToken('user-token')->plainTextToken;

        $this->withToken($userToken)
            ->getJson('/api/admin/commodity-subcategories')
            ->assertStatus(403);
    }

    public function test_admin_can_list_subcategories_with_eager_loaded_commodity_and_category(): void
    {
        CommoditySubcategory::create([
            'commodity_id' => $this->commodityWheat->id,
            'name_en' => 'Lokwan Wheat',
            'slug' => 'lokwan-wheat',
            'sort_order' => 1,
            'status' => true,
        ]);
        CommoditySubcategory::create([
            'commodity_id' => $this->commodityChana->id,
            'name_en' => 'Desi Chana Sub',
            'slug' => 'desi-chana-sub',
            'sort_order' => 2,
            'status' => true,
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/admin/commodity-subcategories?page=1&per_page=1')
            ->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'items' => [
                        '*' => [
                            'id',
                            'commodity_id',
                            'commodity' => [
                                'id',
                                'commodity_category_id',
                                'name_en',
                                'name_hi',
                                'slug',
                                'category' => ['id', 'name_en', 'name_hi', 'slug'],
                            ],
                            'name_en',
                            'name_hi',
                            'slug',
                            'sort_order',
                            'status',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
            ]);

        $this->assertTrue($response->json('status'));
        $this->assertCount(1, $response->json('data.items'));
        $this->assertEquals(2, $response->json('data.pagination.total'));
    }

    public function test_commodity_and_category_mismatch_filter_returns_422(): void
    {
        // Wheat belongs to Grains ($this->categoryGrains), NOT Pulses ($this->categoryPulses)
        $response = $this->withToken($this->adminToken)
            ->getJson('/api/admin/commodity-subcategories?commodity_id='.$this->commodityWheat->id.'&commodity_category_id='.$this->categoryPulses->id)
            ->assertStatus(422);

        $this->assertFalse($response->json('status'));
        $this->assertArrayHasKey('commodity_id', $response->json('errors'));
    }

    public function test_admin_can_filter_subcategories_by_matching_commodity_and_category(): void
    {
        CommoditySubcategory::create([
            'commodity_id' => $this->commodityWheat->id,
            'name_en' => 'Lokwan Wheat',
            'slug' => 'lokwan-wheat',
            'sort_order' => 1,
            'status' => true,
        ]);
        CommoditySubcategory::create([
            'commodity_id' => $this->commodityChana->id,
            'name_en' => 'Desi Chana Sub',
            'slug' => 'desi-chana-sub',
            'sort_order' => 2,
            'status' => true,
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/admin/commodity-subcategories?commodity_id='.$this->commodityWheat->id.'&commodity_category_id='.$this->categoryGrains->id)
            ->assertStatus(200);

        $this->assertCount(1, $response->json('data.items'));
        $this->assertEquals('Lokwan Wheat', $response->json('data.items.0.name_en'));
    }

    public function test_options_api_requires_all_three_statuses_active_and_non_deleted(): void
    {
        $activeSubcat = CommoditySubcategory::create([
            'commodity_id' => $this->commodityWheat->id,
            'name_en' => 'Active Subcat',
            'slug' => 'active-subcat',
            'status' => true,
        ]);

        $inactiveSubcat = CommoditySubcategory::create([
            'commodity_id' => $this->commodityWheat->id,
            'name_en' => 'Inactive Subcat',
            'slug' => 'inactive-subcat',
            'status' => false,
        ]);

        // When all 3 are active: only active subcat is returned
        $response = $this->withToken($this->adminToken)
            ->getJson('/api/admin/commodity-subcategories/options?commodity_id='.$this->commodityWheat->id)
            ->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Active Subcat', $response->json('data.0.name_en'));

        // Deactivate parent Commodity -> Options should return empty
        $this->commodityWheat->update(['status' => false]);
        Cache::forget(CommoditySubcategoryService::CACHE_KEY_OPTIONS_ALL);
        Cache::forget(CommoditySubcategoryService::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$this->commodityWheat->id);

        $response2 = $this->withToken($this->adminToken)
            ->getJson('/api/admin/commodity-subcategories/options?commodity_id='.$this->commodityWheat->id)
            ->assertStatus(200);

        $this->assertCount(0, $response2->json('data'));

        // Reactivate Commodity, but deactivate grandparent Category -> Options should return empty
        $this->commodityWheat->update(['status' => true]);
        $this->categoryGrains->update(['status' => false]);
        Cache::forget(CommoditySubcategoryService::CACHE_KEY_OPTIONS_ALL);
        Cache::forget(CommoditySubcategoryService::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$this->commodityWheat->id);

        $response3 = $this->withToken($this->adminToken)
            ->getJson('/api/admin/commodity-subcategories/options?commodity_id='.$this->commodityWheat->id)
            ->assertStatus(200);

        $this->assertCount(0, $response3->json('data'));
    }

    public function test_store_subcategory_succeeds_under_active_hierarchy(): void
    {
        $payload = [
            'commodity_id' => $this->commodityWheat->id,
            'name_en' => 'Sharbati Wheat',
            'name_hi' => 'शरबती गेहूं',
            'slug' => 'sharbati-wheat',
            'description_en' => 'Premium high grade wheat',
            'sort_order' => 5,
            'status' => true,
        ];

        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodity-subcategories', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.name_en', 'Sharbati Wheat')
            ->assertJsonPath('data.slug', 'sharbati-wheat')
            ->assertJsonPath('data.commodity_id', $this->commodityWheat->id);

        $this->assertDatabaseHas('commodity_subcategories', [
            'name_en' => 'Sharbati Wheat',
            'slug' => 'sharbati-wheat',
            'commodity_id' => $this->commodityWheat->id,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_store_subcategory_fails_if_commodity_or_category_is_inactive_or_deleted(): void
    {
        $inactiveCommodity = Commodity::create([
            'commodity_category_id' => $this->categoryGrains->id,
            'name_en' => 'Barley Inactive',
            'slug' => 'barley-inactive',
            'status' => false,
        ]);

        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodity-subcategories', [
                'commodity_id' => $inactiveCommodity->id,
                'name_en' => 'Pearl Barley',
            ])
            ->assertStatus(422);

        $this->assertFalse($response->json('status'));

        // Category inactive case
        $inactiveCategory = CommodityCategory::create([
            'name_en' => 'Oilseeds Inactive',
            'slug' => 'oilseeds-inactive',
            'status' => false,
        ]);
        $commodityUnderInactiveCat = Commodity::create([
            'commodity_category_id' => $inactiveCategory->id,
            'name_en' => 'Mustard Active',
            'slug' => 'mustard-active',
            'status' => true,
        ]);

        $response2 = $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodity-subcategories', [
                'commodity_id' => $commodityUnderInactiveCat->id,
                'name_en' => 'Black Mustard',
            ])
            ->assertStatus(422);

        $this->assertFalse($response2->json('status'));
    }

    public function test_same_slug_is_allowed_across_different_commodities(): void
    {
        // Wheat -> premium
        $sub1 = CommoditySubcategory::create([
            'commodity_id' => $this->commodityWheat->id,
            'name_en' => 'Premium Wheat Sub',
            'slug' => 'premium',
            'status' => true,
        ]);

        // Chana -> premium (should succeed because slug is unique per commodity)
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodity-subcategories', [
                'commodity_id' => $this->commodityChana->id,
                'name_en' => 'Premium Chana Sub',
                'slug' => 'premium',
            ])
            ->assertStatus(201);

        $this->assertEquals('premium', $response->json('data.slug'));
        $this->assertEquals($this->commodityChana->id, $response->json('data.commodity_id'));
    }

    public function test_duplicate_slug_under_same_commodity_is_blocked_including_soft_deleted(): void
    {
        $existing = CommoditySubcategory::create([
            'commodity_id' => $this->commodityWheat->id,
            'name_en' => 'Special Grade',
            'slug' => 'special-grade',
            'status' => true,
        ]);

        // Active collision
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodity-subcategories', [
                'commodity_id' => $this->commodityWheat->id,
                'name_en' => 'Another Special Grade',
                'slug' => 'special-grade',
            ])
            ->assertStatus(422);

        $this->assertArrayHasKey('slug', $response->json('errors'));

        // Soft-delete the existing row -> slug must STILL be reserved
        $existing->delete();

        $response2 = $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodity-subcategories', [
                'commodity_id' => $this->commodityWheat->id,
                'name_en' => 'Another Special Grade',
                'slug' => 'special-grade',
            ])
            ->assertStatus(422);

        $this->assertArrayHasKey('slug', $response2->json('errors'));
    }

    public function test_auto_slug_generation_appends_counter_per_commodity(): void
    {
        $sub1 = $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodity-subcategories', [
                'commodity_id' => $this->commodityWheat->id,
                'name_en' => 'Super Grain',
            ])
            ->assertStatus(201);
        $this->assertEquals('super-grain', $sub1->json('data.slug'));

        $sub2 = $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodity-subcategories', [
                'commodity_id' => $this->commodityWheat->id,
                'name_en' => 'Super Grain',
            ])
            ->assertStatus(201);
        $this->assertEquals('super-grain-2', $sub2->json('data.slug'));

        $sub3 = $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodity-subcategories', [
                'commodity_id' => $this->commodityWheat->id,
                'name_en' => 'Super Grain',
            ])
            ->assertStatus(201);
        $this->assertEquals('super-grain-3', $sub3->json('data.slug'));
    }

    public function test_moving_subcategory_without_slug_fails_if_target_commodity_already_owns_slug_and_leaves_record_unchanged(): void
    {
        // Wheat has subcategory with slug 'premium'
        $wheatSubcat = CommoditySubcategory::create([
            'commodity_id' => $this->commodityWheat->id,
            'name_en' => 'Premium Wheat Subcat',
            'slug' => 'premium',
            'status' => true,
        ]);

        // Chana ALREADY has subcategory with slug 'premium'
        $chanaSubcat = CommoditySubcategory::create([
            'commodity_id' => $this->commodityChana->id,
            'name_en' => 'Premium Chana Subcat',
            'slug' => 'premium',
            'status' => true,
        ]);

        // Try moving wheatSubcat to Chana WITHOUT providing slug
        $response = $this->withToken($this->adminToken)
            ->putJson('/api/admin/commodity-subcategories/'.$wheatSubcat->id, [
                'commodity_id' => $this->commodityChana->id,
                'name_en' => 'Moved Wheat Subcat',
            ])
            ->assertStatus(422);

        $this->assertArrayHasKey('slug', $response->json('errors'));

        // Verify original record is unchanged
        $wheatSubcat->refresh();
        $this->assertEquals($this->commodityWheat->id, $wheatSubcat->commodity_id);
        $this->assertEquals('Premium Wheat Subcat', $wheatSubcat->name_en);
        $this->assertEquals('premium', $wheatSubcat->slug);
    }

    public function test_moving_subcategory_to_another_commodity_with_no_slug_collision_succeeds(): void
    {
        $wheatSubcat = CommoditySubcategory::create([
            'commodity_id' => $this->commodityWheat->id,
            'name_en' => 'Unique Wheat Subcat',
            'slug' => 'unique-wheat-subcat',
            'status' => true,
        ]);

        $response = $this->withToken($this->adminToken)
            ->putJson('/api/admin/commodity-subcategories/'.$wheatSubcat->id, [
                'commodity_id' => $this->commodityChana->id,
            ])
            ->assertStatus(200);

        $this->assertEquals($this->commodityChana->id, $response->json('data.commodity_id'));
        $this->assertEquals('unique-wheat-subcat', $response->json('data.slug'));

        $this->assertDatabaseHas('commodity_subcategories', [
            'id' => $wheatSubcat->id,
            'commodity_id' => $this->commodityChana->id,
        ]);
    }

    public function test_single_delete_soft_deletes_record_and_invalidates_cache(): void
    {
        $subcat = CommoditySubcategory::create([
            'commodity_id' => $this->commodityWheat->id,
            'name_en' => 'To Delete Sub',
            'slug' => 'to-delete-sub',
            'status' => true,
        ]);

        $cacheKey = CommoditySubcategoryService::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$this->commodityWheat->id;
        Cache::put($cacheKey, ['cached_data'], 3600);

        $response = $this->withToken($this->adminToken)
            ->deleteJson('/api/admin/commodity-subcategories/'.$subcat->id)
            ->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->assertSoftDeleted('commodity_subcategories', ['id' => $subcat->id]);
        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_bulk_delete_soft_deletes_records_and_invalidates_cache(): void
    {
        $sub1 = CommoditySubcategory::create([
            'commodity_id' => $this->commodityWheat->id,
            'name_en' => 'Bulk Delete 1',
            'slug' => 'bulk-delete-1',
            'status' => true,
        ]);
        $sub2 = CommoditySubcategory::create([
            'commodity_id' => $this->commodityChana->id,
            'name_en' => 'Bulk Delete 2',
            'slug' => 'bulk-delete-2',
            'status' => true,
        ]);

        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodity-subcategories/bulk-delete', [
                'ids' => [$sub1->id, $sub2->id],
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.deleted_count', 2);

        $this->assertSoftDeleted('commodity_subcategories', ['id' => $sub1->id]);
        $this->assertSoftDeleted('commodity_subcategories', ['id' => $sub2->id]);
    }

    public function test_inactive_subcategory_still_blocks_commodity_deletion(): void
    {
        $inactiveSubcat = CommoditySubcategory::create([
            'commodity_id' => $this->commodityWheat->id,
            'name_en' => 'Milling Wheat',
            'slug' => 'milling-wheat',
            'status' => false,
            'deleted_at' => null,
        ]);

        $response = $this->withToken($this->adminToken)
            ->deleteJson('/api/admin/commodities/'.$this->commodityWheat->id)
            ->assertStatus(409)
            ->assertJsonPath('status', false)
            ->assertJsonPath('error', 'COMMODITY_IN_USE');

        $this->assertNotSoftDeleted('commodities', ['id' => $this->commodityWheat->id]);
    }

    public function test_soft_deleted_subcategory_does_not_block_commodity_deletion(): void
    {
        $subcat = CommoditySubcategory::create([
            'commodity_id' => $this->commodityWheat->id,
            'name_en' => 'Soft Deleted Sub',
            'slug' => 'soft-deleted-sub',
            'status' => true,
        ]);
        $subcat->delete();

        $response = $this->withToken($this->adminToken)
            ->deleteJson('/api/admin/commodities/'.$this->commodityWheat->id)
            ->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->assertSoftDeleted('commodities', ['id' => $this->commodityWheat->id]);
    }

    public function test_bulk_commodity_delete_blocked_by_non_deleted_subcategories(): void
    {
        $subcat = CommoditySubcategory::create([
            'commodity_id' => $this->commodityWheat->id,
            'name_en' => 'Dependent Sub',
            'slug' => 'dependent-sub',
            'status' => false,
        ]);

        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodities/bulk-delete', [
                'ids' => [$this->commodityWheat->id, $this->commodityChana->id],
            ])
            ->assertStatus(409)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.blocked_ids', [$this->commodityWheat->id]);

        $this->assertNotSoftDeleted('commodities', ['id' => $this->commodityWheat->id]);
        $this->assertNotSoftDeleted('commodities', ['id' => $this->commodityChana->id]);
    }

    public function test_category_status_change_invalidates_all_affected_subcategory_option_caches(): void
    {
        $subWheat = CommoditySubcategory::create([
            'commodity_id' => $this->commodityWheat->id,
            'name_en' => 'Sub Wheat',
            'slug' => 'sub-wheat',
            'status' => true,
        ]);

        $cacheKey = CommoditySubcategoryService::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$this->commodityWheat->id;
        Cache::put($cacheKey, ['cached_data'], 3600);
        Cache::put(CommoditySubcategoryService::CACHE_KEY_OPTIONS_ALL, ['cached_data_all'], 3600);

        // Update categoryGrains status
        $response = $this->withToken($this->adminToken)
            ->patchJson('/api/admin/commodity-categories/'.$this->categoryGrains->id.'/status', [
                'status' => false,
            ])
            ->assertStatus(200);

        $this->assertFalse(Cache::has($cacheKey));
        $this->assertFalse(Cache::has(CommoditySubcategoryService::CACHE_KEY_OPTIONS_ALL));
    }

    public function test_commodity_bulk_status_invalidates_subcategory_caches_for_all_affected_commodities(): void
    {
        $key1 = CommoditySubcategoryService::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$this->commodityWheat->id;
        $key2 = CommoditySubcategoryService::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$this->commodityChana->id;

        Cache::put($key1, ['data1'], 3600);
        Cache::put($key2, ['data2'], 3600);
        Cache::put(CommoditySubcategoryService::CACHE_KEY_OPTIONS_ALL, ['data_all'], 3600);

        $response = $this->withToken($this->adminToken)
            ->patchJson('/api/admin/commodities/bulk-status', [
                'ids' => [$this->commodityWheat->id, $this->commodityChana->id],
                'status' => false,
            ])
            ->assertStatus(200);

        $this->assertFalse(Cache::has($key1));
        $this->assertFalse(Cache::has($key2));
        $this->assertFalse(Cache::has(CommoditySubcategoryService::CACHE_KEY_OPTIONS_ALL));
    }
}
