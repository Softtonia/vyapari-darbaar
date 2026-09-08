<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use App\Models\User;
use App\Services\CommodityCategoryService;
use App\Services\CommodityService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CommodityManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected string $adminToken;

    protected CommodityCategory $categoryGrains;

    protected CommodityCategory $categoryPulses;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->admin = Admin::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'name' => 'Super Admin',
            'email' => 'admin.commodities.crud@example.com',
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

        Cache::forget(CommodityService::CACHE_KEY_OPTIONS_ALL);
        Cache::forget(CommodityService::CACHE_KEY_OPTIONS_CATEGORY_PREFIX.$this->categoryGrains->id);
        Cache::forget(CommodityService::CACHE_KEY_OPTIONS_CATEGORY_PREFIX.$this->categoryPulses->id);
    }

    public function test_unauthenticated_requests_are_blocked(): void
    {
        $this->getJson('/api/admin/commodities')->assertStatus(401);
        $this->getJson('/api/admin/commodities/options')->assertStatus(401);
        $this->postJson('/api/admin/commodities', [])->assertStatus(401);
        $this->getJson('/api/admin/commodities/1')->assertStatus(401);
        $this->putJson('/api/admin/commodities/1', [])->assertStatus(401);
        $this->patchJson('/api/admin/commodities/1/status', [])->assertStatus(401);
        $this->deleteJson('/api/admin/commodities/1')->assertStatus(401);
        $this->postJson('/api/admin/commodities/bulk-delete', [])->assertStatus(401);
    }

    public function test_user_token_is_forbidden_from_admin_commodities(): void
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
            ->getJson('/api/admin/commodities')
            ->assertStatus(403);
    }

    public function test_admin_can_list_commodities_with_eager_loaded_category_and_pagination(): void
    {
        Commodity::create([
            'commodity_category_id' => $this->categoryGrains->id,
            'name_en' => 'Wheat',
            'slug' => 'wheat',
            'sort_order' => 1,
            'status' => true,
        ]);
        Commodity::create([
            'commodity_category_id' => $this->categoryPulses->id,
            'name_en' => 'Chana',
            'slug' => 'chana',
            'sort_order' => 2,
            'status' => true,
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/admin/commodities?page=1&per_page=1')
            ->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'items' => [
                        '*' => [
                            'id',
                            'commodity_category_id',
                            'category' => ['id', 'name_en', 'name_hi', 'slug'],
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

    public function test_per_page_greater_than_100_returns_422(): void
    {
        $this->withToken($this->adminToken)
            ->getJson('/api/admin/commodities?per_page=500')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_invalid_query_parameters_return_422(): void
    {
        $this->withToken($this->adminToken)
            ->getJson('/api/admin/commodities?status=abc')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->withToken($this->adminToken)
            ->getJson('/api/admin/commodities?sort_by=invalid_column')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sort_by']);

        $this->withToken($this->adminToken)
            ->getJson('/api/admin/commodities?sort_order=invalid_order')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sort_order']);

        $this->withToken($this->adminToken)
            ->getJson('/api/admin/commodities?commodity_category_id=99999')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['commodity_category_id']);
    }

    public function test_search_and_category_filtering(): void
    {
        Commodity::create(['commodity_category_id' => $this->categoryGrains->id, 'name_en' => 'Wheat', 'name_hi' => 'गेहूं', 'slug' => 'wheat', 'status' => true]);
        Commodity::create(['commodity_category_id' => $this->categoryGrains->id, 'name_en' => 'Rice', 'name_hi' => 'चावल', 'slug' => 'rice', 'status' => true]);
        Commodity::create(['commodity_category_id' => $this->categoryPulses->id, 'name_en' => 'Chana', 'name_hi' => 'चना', 'slug' => 'chana', 'status' => true]);

        // Search English
        $resEn = $this->withToken($this->adminToken)->getJson('/api/admin/commodities?search=wheat')->assertStatus(200);
        $this->assertCount(1, $resEn->json('data.items'));
        $this->assertEquals('Wheat', $resEn->json('data.items.0.name_en'));

        // Search Hindi
        $resHi = $this->withToken($this->adminToken)->getJson('/api/admin/commodities?search=चना')->assertStatus(200);
        $this->assertCount(1, $resHi->json('data.items'));
        $this->assertEquals('Chana', $resHi->json('data.items.0.name_en'));

        // Filter Category
        $resCat = $this->withToken($this->adminToken)->getJson("/api/admin/commodities?commodity_category_id={$this->categoryGrains->id}")->assertStatus(200);
        $this->assertCount(2, $resCat->json('data.items'));
    }

    public function test_admin_can_create_commodity_with_auto_slug_and_validation(): void
    {
        $payload = [
            'commodity_category_id' => $this->categoryGrains->id,
            'name_en' => '  Barley Malt  ',
            'name_hi' => '  जौ माल्ट  ',
            'description_en' => '  Barley grains for brewing  ',
            'sort_order' => 5,
            'status' => true,
        ];

        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodities', $payload)
            ->assertStatus(201)
            ->assertJson([
                'status' => true,
                'message' => 'Commodity created successfully.',
                'data' => [
                    'commodity_category_id' => $this->categoryGrains->id,
                    'name_en' => 'Barley Malt',
                    'name_hi' => 'जौ माल्ट',
                    'slug' => 'barley-malt',
                    'sort_order' => 5,
                    'status' => true,
                ],
            ]);

        $this->assertDatabaseHas('commodities', [
            'name_en' => 'Barley Malt',
            'slug' => 'barley-malt',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_store_rejects_inactive_or_soft_deleted_category(): void
    {
        $inactiveCat = CommodityCategory::create(['name_en' => 'Inactive Cat', 'slug' => 'inactive-cat', 'status' => false]);
        $deletedCat = CommodityCategory::create(['name_en' => 'Deleted Cat', 'slug' => 'deleted-cat', 'status' => true]);
        $deletedCat->delete();

        // Inactive category rejected on store
        $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodities', [
                'commodity_category_id' => $inactiveCat->id,
                'name_en' => 'Test Item',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['commodity_category_id']);

        // Deleted category rejected
        $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodities', [
                'commodity_category_id' => $deletedCat->id,
                'name_en' => 'Test Item 2',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['commodity_category_id']);
    }

    public function test_duplicate_slug_and_soft_deleted_slug_reservations_on_store(): void
    {
        $existing = Commodity::create([
            'commodity_category_id' => $this->categoryGrains->id,
            'name_en' => 'Maize',
            'slug' => 'maize',
        ]);

        // Duplicate active slug
        $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodities', [
                'commodity_category_id' => $this->categoryGrains->id,
                'name_en' => 'Other Maize',
                'slug' => 'maize',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);

        $existing->delete(); // Soft delete

        // Soft-deleted slug remains reserved
        $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodities', [
                'commodity_category_id' => $this->categoryGrains->id,
                'name_en' => 'New Maize',
                'slug' => 'maize',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);

        // Auto-slug collision resolves deterministically with withTrashed()
        /** @var CommodityService $service */
        $service = app(CommodityService::class);
        $uniqueSlug = $service->generateUniqueSlug('maize');
        $this->assertEquals('maize-2', $uniqueSlug);
    }

    public function test_admin_can_view_commodity_detail(): void
    {
        $commodity = Commodity::create([
            'commodity_category_id' => $this->categoryGrains->id,
            'name_en' => 'Detailed Wheat',
            'slug' => 'detailed-wheat',
            'description_en' => 'Full specification description text.',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson("/api/admin/commodities/{$commodity->id}")
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'id' => $commodity->id,
                    'name_en' => 'Detailed Wheat',
                    'slug' => 'detailed-wheat',
                    'description_en' => 'Full specification description text.',
                    'category' => [
                        'id' => $this->categoryGrains->id,
                        'name_en' => 'Grains',
                    ],
                    'creator' => [
                        'id' => $this->admin->id,
                        'name' => $this->admin->name,
                    ],
                ],
            ]);
    }

    public function test_detail_returns_404_for_non_existent_or_soft_deleted(): void
    {
        $this->withToken($this->adminToken)->getJson('/api/admin/commodities/99999')->assertStatus(404);

        $commodity = Commodity::create(['commodity_category_id' => $this->categoryGrains->id, 'name_en' => 'Deleted Item', 'slug' => 'deleted-item']);
        $commodity->delete();

        $this->withToken($this->adminToken)->getJson("/api/admin/commodities/{$commodity->id}")->assertStatus(404);
    }

    public function test_update_commodity_category_and_omitted_slug(): void
    {
        $commodity = Commodity::create([
            'commodity_category_id' => $this->categoryGrains->id,
            'name_en' => 'Original Grain Item',
            'slug' => 'original-grain-item',
        ]);

        // 1. Update without slug retains existing slug
        $response1 = $this->withToken($this->adminToken)
            ->putJson("/api/admin/commodities/{$commodity->id}", [
                'name_en' => 'Updated Grain Item',
            ])
            ->assertStatus(200);
        $this->assertEquals('Updated Grain Item', $response1->json('data.name_en'));
        $this->assertEquals('original-grain-item', $response1->json('data.slug'));

        // 2. Reassign to valid active category
        $response2 = $this->withToken($this->adminToken)
            ->putJson("/api/admin/commodities/{$commodity->id}", [
                'commodity_category_id' => $this->categoryPulses->id,
            ])
            ->assertStatus(200);
        $this->assertEquals($this->categoryPulses->id, $response2->json('data.commodity_category_id'));
    }

    public function test_editing_ordinary_fields_under_existing_inactive_parent_is_allowed(): void
    {
        $inactiveCat = CommodityCategory::create(['name_en' => 'Old Inactive Cat', 'slug' => 'old-inactive-cat', 'status' => false]);
        $commodity = Commodity::create([
            'commodity_category_id' => $inactiveCat->id,
            'name_en' => 'Existing Item',
            'slug' => 'existing-item',
        ]);

        // Editing name without changing category should succeed
        $response = $this->withToken($this->adminToken)
            ->putJson("/api/admin/commodities/{$commodity->id}", [
                'name_en' => 'Existing Item Updated',
            ])
            ->assertStatus(200);

        $this->assertEquals('Existing Item Updated', $response->json('data.name_en'));

        // Attempting to change to ANOTHER inactive category is rejected
        $anotherInactiveCat = CommodityCategory::create(['name_en' => 'Another Inactive', 'slug' => 'another-inactive', 'status' => false]);
        $this->withToken($this->adminToken)
            ->putJson("/api/admin/commodities/{$commodity->id}", [
                'commodity_category_id' => $anotherInactiveCat->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['commodity_category_id']);
    }

    public function test_status_update_and_bulk_status_update(): void
    {
        $c1 = Commodity::create(['commodity_category_id' => $this->categoryGrains->id, 'name_en' => 'C1', 'slug' => 'c1', 'status' => true]);
        $c2 = Commodity::create(['commodity_category_id' => $this->categoryPulses->id, 'name_en' => 'C2', 'slug' => 'c2', 'status' => true]);

        // Single status
        $this->withToken($this->adminToken)
            ->patchJson("/api/admin/commodities/{$c1->id}/status", ['status' => false])
            ->assertStatus(200);
        $this->assertFalse((bool) $c1->fresh()->status);

        // Bulk status across multiple categories
        $this->withToken($this->adminToken)
            ->patchJson('/api/admin/commodities/bulk-status', [
                'ids' => [$c1->id, $c2->id],
                'status' => true,
            ])
            ->assertStatus(200)
            ->assertJson(['data' => ['updated_count' => 2]]);

        $this->assertTrue((bool) $c1->fresh()->status);
        $this->assertTrue((bool) $c2->fresh()->status);
    }

    public function test_single_delete_and_bulk_delete_with_cache_invalidation(): void
    {
        $c1 = Commodity::create(['commodity_category_id' => $this->categoryGrains->id, 'name_en' => 'Del 1', 'slug' => 'del-1']);
        $c2 = Commodity::create(['commodity_category_id' => $this->categoryPulses->id, 'name_en' => 'Del 2', 'slug' => 'del-2']);

        Cache::put(CommodityService::CACHE_KEY_OPTIONS_ALL, ['cached']);
        Cache::put(CommodityService::CACHE_KEY_OPTIONS_CATEGORY_PREFIX.$this->categoryGrains->id, ['cached']);

        // Single delete
        $this->withToken($this->adminToken)->deleteJson("/api/admin/commodities/{$c1->id}")->assertStatus(200);
        $this->assertSoftDeleted('commodities', ['id' => $c1->id]);
        $this->assertNull(Cache::get(CommodityService::CACHE_KEY_OPTIONS_ALL));
        $this->assertNull(Cache::get(CommodityService::CACHE_KEY_OPTIONS_CATEGORY_PREFIX.$this->categoryGrains->id));

        // DELETE /bulk-delete must not be registered
        $delBulkRes = $this->withToken($this->adminToken)->deleteJson('/api/admin/commodities/bulk-delete');
        $this->assertContains($delBulkRes->status(), [404, 405]);

        // POST /bulk-delete
        $this->withToken($this->adminToken)->postJson('/api/admin/commodities/bulk-delete', [
            'ids' => [$c2->id],
        ])->assertStatus(200)->assertJson(['data' => ['deleted_count' => 1]]);
        $this->assertSoftDeleted('commodities', ['id' => $c2->id]);
    }

    public function test_exact_seeded_commodity_names_and_categories_are_correct(): void
    {
        $this->seed(\Database\Seeders\CommodityCategorySeeder::class);
        $this->seed(\Database\Seeders\CommoditySeeder::class);

        $expected = [
            'wheat' => 'Wheat',
            'chana' => 'Chana',
            'soybean' => 'Soybean',
            'mustard-oil' => 'Mustard Oil',
            'jeera-cumin' => 'Jeera (Cumin)',
            'turmeric' => 'Turmeric',
            'sugar' => 'Sugar',
            'almonds' => 'Almonds',
        ];

        foreach ($expected as $slug => $nameEn) {
            $this->assertDatabaseHas('commodities', [
                'slug' => $slug,
                'name_en' => $nameEn,
            ]);
        }
    }

    public function test_options_excludes_commodities_whose_parent_category_is_inactive(): void
    {
        $activeCat = CommodityCategory::create(['name_en' => 'Active Cat', 'slug' => 'active-cat-opt', 'status' => true]);
        $inactiveCat = CommodityCategory::create(['name_en' => 'Inactive Cat', 'slug' => 'inactive-cat-opt', 'status' => false]);

        $commActive = Commodity::create(['commodity_category_id' => $activeCat->id, 'name_en' => 'Active Comm', 'slug' => 'active-comm', 'status' => true]);
        $commUnderInactive = Commodity::create(['commodity_category_id' => $inactiveCat->id, 'name_en' => 'Hidden Comm', 'slug' => 'hidden-comm', 'status' => true]);

        // Options call
        $response = $this->withToken($this->adminToken)->getJson('/api/admin/commodities/options')->assertStatus(200);
        $items = $response->json('data');

        $slugs = array_column($items, 'slug');
        $this->assertContains('active-comm', $slugs);
        $this->assertNotContains('hidden-comm', $slugs);

        // Reactivating category restores commodity in options
        $inactiveCat->update(['status' => true]);
        app(CommodityCategoryService::class)->clearCache((int) $inactiveCat->id);

        $responseReactivated = $this->withToken($this->adminToken)->getJson('/api/admin/commodities/options')->assertStatus(200);
        $slugsReactivated = array_column($responseReactivated->json('data'), 'slug');
        $this->assertContains('hidden-comm', $slugsReactivated);
    }

    public function test_category_with_commodities_cannot_be_deleted_returning_409(): void
    {
        $cat = CommodityCategory::create(['name_en' => 'Protected Cat', 'slug' => 'protected-cat', 'status' => true]);
        $commodity = Commodity::create(['commodity_category_id' => $cat->id, 'name_en' => 'Child Comm', 'slug' => 'child-comm']);

        // Single delete category blocked
        $response = $this->withToken($this->adminToken)
            ->deleteJson("/api/admin/commodity-categories/{$cat->id}")
            ->assertStatus(409)
            ->assertJson([
                'status' => false,
                'message' => 'Commodity category cannot be deleted because it has commodities assigned.',
                'error' => 'CATEGORY_IN_USE',
            ]);

        $this->assertDatabaseHas('commodity_categories', ['id' => $cat->id, 'deleted_at' => null]);

        // Bulk delete category blocked atomically
        $catFree = CommodityCategory::create(['name_en' => 'Free Cat', 'slug' => 'free-cat', 'status' => true]);

        $bulkRes = $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodity-categories/bulk-delete', [
                'ids' => [$cat->id, $catFree->id],
            ])
            ->assertStatus(409)
            ->assertJson([
                'status' => false,
                'message' => 'Some commodity categories cannot be deleted because they are in use.',
                'data' => [
                    'blocked_ids' => [$cat->id],
                ],
            ]);

        // Atomic behavior: Neither category is deleted
        $this->assertDatabaseHas('commodity_categories', ['id' => $cat->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('commodity_categories', ['id' => $catFree->id, 'deleted_at' => null]);
    }
}
