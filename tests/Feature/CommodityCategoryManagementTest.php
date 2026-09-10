<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CommodityCategory;
use App\Models\User;
use App\Services\CommodityCategoryService;
use Database\Seeders\CommodityCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CommodityCategoryManagementTest extends TestCase
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
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'name' => 'Super Admin',
            'email' => 'admin.commodities@example.com',
            'password' => Hash::make('AdminPass@12345'),
            'status' => 'active',
        ]);

        $this->admin->assignRole('admin');
        $this->adminToken = $this->admin->createToken('admin-token')->plainTextToken;

        Cache::forget(CommodityCategoryService::CACHE_KEY_OPTIONS);
        Cache::forget(CommodityCategoryService::CACHE_KEY_ACTIVE);
    }

    public function test_unauthenticated_requests_are_blocked(): void
    {
        $this->getJson('/api/admin/commodity-categories')->assertStatus(401);
        $this->getJson('/api/admin/commodity-categories/options')->assertStatus(401);
        $this->postJson('/api/admin/commodity-categories', [])->assertStatus(401);
        $this->getJson('/api/admin/commodity-categories/1')->assertStatus(401);
        $this->putJson('/api/admin/commodity-categories/1', [])->assertStatus(401);
        $this->patchJson('/api/admin/commodity-categories/1/status', [])->assertStatus(401);
        $this->deleteJson('/api/admin/commodity-categories/1')->assertStatus(401);
        $this->postJson('/api/admin/commodity-categories/bulk-delete', [])->assertStatus(401);
    }

    public function test_user_token_is_forbidden_from_admin_commodity_category_endpoints(): void
    {
        $user = User::create([
            'first_name' => 'Regular',
            'last_name' => 'Trader',
            'name' => 'Regular Trader',
            'phone_number' => '+919876543210',
            'username' => 'reg.trader',
            'email' => 'trader@example.com',
            'password' => Hash::make('Secret123#'),
            'status' => 'active',
        ]);

        $userToken = $user->createToken('user-token')->plainTextToken;

        $this->withToken($userToken)
            ->getJson('/api/admin/commodity-categories')
            ->assertStatus(403);
    }

    public function test_admin_can_list_categories_with_pagination_and_pruned_columns(): void
    {
        CommodityCategory::create([
            'name' => 'Category A',
            'slug' => 'category-a',
            'description' => 'Long list description that should be omitted or kept lean',
            'sort_order' => 1,
            'status' => true,
        ]);
        CommodityCategory::create([
            'name' => 'Category B',
            'slug' => 'category-b',
            'sort_order' => 2,
            'status' => true,
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/admin/commodity-categories?page=1&per_page=1')
            ->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'items' => [
                        '*' => [
                            'id',
                            'name',
                            'slug',
                            'sort_order',
                            'status',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'pagination' => [
                        'current_page',
                        'per_page',
                        'total',
                        'last_page',
                    ],
                ],
            ]);

        $this->assertTrue($response->json('status'));
        $this->assertCount(1, $response->json('data.items'));
        $this->assertEquals(2, $response->json('data.pagination.total'));
    }

    public function test_per_page_greater_than_100_returns_422(): void
    {
        $this->withToken($this->adminToken)
            ->getJson('/api/admin/commodity-categories?per_page=500')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_invalid_status_query_returns_422(): void
    {
        $this->withToken($this->adminToken)
            ->getJson('/api/admin/commodity-categories?status=abc')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_invalid_sort_by_returns_422(): void
    {
        $this->withToken($this->adminToken)
            ->getJson('/api/admin/commodity-categories?sort_by=non_existent_column')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sort_by']);
    }

    public function test_invalid_sort_order_returns_422(): void
    {
        $this->withToken($this->adminToken)
            ->getJson('/api/admin/commodity-categories?sort_order=sideways')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sort_order']);
    }

    public function test_search_filters_by_name_and_slug(): void
    {
        CommodityCategory::create([
            'name' => 'Grains',
            'slug' => 'grains',
            'sort_order' => 1,
            'status' => true,
        ]);
        CommodityCategory::create([
            'name' => 'Spices',
            'slug' => 'spices',
            'sort_order' => 2,
            'status' => true,
        ]);

        // Search by Name
        $responseName = $this->withToken($this->adminToken)
            ->getJson('/api/admin/commodity-categories?search=grain')
            ->assertStatus(200);
        $this->assertCount(1, $responseName->json('data.items'));
        $this->assertEquals('Grains', $responseName->json('data.items.0.name'));

        // Search by Slug
        $responseSlug = $this->withToken($this->adminToken)
            ->getJson('/api/admin/commodity-categories?search=spices')
            ->assertStatus(200);
        $this->assertCount(1, $responseSlug->json('data.items'));
        $this->assertEquals('Spices', $responseSlug->json('data.items.0.name'));
    }

    public function test_status_filter_works(): void
    {
        CommodityCategory::create([
            'name' => 'Active Cat',
            'slug' => 'active-cat',
            'status' => true,
        ]);
        CommodityCategory::create([
            'name' => 'Inactive Cat',
            'slug' => 'inactive-cat',
            'status' => false,
        ]);

        $responseActive = $this->withToken($this->adminToken)
            ->getJson('/api/admin/commodity-categories?status=1')
            ->assertStatus(200);
        $this->assertCount(1, $responseActive->json('data.items'));
        $this->assertEquals('Active Cat', $responseActive->json('data.items.0.name'));

        $responseInactive = $this->withToken($this->adminToken)
            ->getJson('/api/admin/commodity-categories?status=0')
            ->assertStatus(200);
        $this->assertCount(1, $responseInactive->json('data.items'));
        $this->assertEquals('Inactive Cat', $responseInactive->json('data.items.0.name'));
    }

    public function test_options_endpoint_returns_only_active_categories_and_caches_result(): void
    {
        CommodityCategory::create(['name' => 'Active 1', 'slug' => 'active-1', 'sort_order' => 1, 'status' => true]);
        CommodityCategory::create(['name' => 'Inactive 1', 'slug' => 'inactive-1', 'sort_order' => 2, 'status' => false]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/admin/commodity-categories/options')
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Commodity category options retrieved successfully.',
            ]);

        $items = $response->json('data');
        $this->assertCount(1, $items);
        $this->assertEquals('Active 1', $items[0]['name']);
        $this->assertEquals('active-1', $items[0]['slug']);
        $this->assertArrayNotHasKey('description', $items[0]);

        // Verify cache hit
        $cached = Cache::get(CommodityCategoryService::CACHE_KEY_OPTIONS);
        $this->assertNotNull($cached);
        $this->assertCount(1, $cached);
    }

    public function test_admin_can_create_category_with_auto_slug_and_trims_input(): void
    {
        $payload = [
            'name' => '  Oilseeds & Pulses  ',
            'description' => '  Various oilseeds  ',
            'sort_order' => 3,
            'status' => true,
        ];

        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodity-categories', $payload)
            ->assertStatus(201)
            ->assertJson([
                'status' => true,
                'message' => 'Commodity category created successfully.',
                'data' => [
                    'name' => 'Oilseeds & Pulses',
                    'slug' => 'oilseeds-pulses',
                    'sort_order' => 3,
                    'status' => true,
                ],
            ]);

        $this->assertDatabaseHas('commodity_categories', [
            'name' => 'Oilseeds & Pulses',
            'slug' => 'oilseeds-pulses',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_supplied_slug_is_normalized_and_validated(): void
    {
        $payload = [
            'name' => 'Custom Category',
            'slug' => '  My Custom--Slug!!  ',
            'sort_order' => 1,
            'status' => true,
        ];

        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodity-categories', $payload)
            ->assertStatus(201);

        $this->assertEquals('my-custom-slug', $response->json('data.slug'));
    }

    public function test_duplicate_slug_is_rejected_in_request_validation(): void
    {
        CommodityCategory::create([
            'name' => 'Grains',
            'slug' => 'grains',
            'status' => true,
        ]);

        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodity-categories', [
                'name' => 'Other Category',
                'slug' => 'grains',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_duplicate_slug_against_soft_deleted_row_is_also_rejected(): void
    {
        $cat = CommodityCategory::create([
            'name' => 'Soft Deleted Category',
            'slug' => 'soft-deleted-cat',
            'status' => true,
        ]);
        $cat->delete(); // Soft delete

        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodity-categories', [
                'name' => 'New Category',
                'slug' => 'soft-deleted-cat',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_auto_slug_considers_soft_deleted_records(): void
    {
        $cat = CommodityCategory::create([
            'name' => 'Wheat Grains',
            'slug' => 'wheat-grains',
        ]);
        $cat->delete(); // Soft deleted

        /** @var CommodityCategoryService $service */
        $service = app(CommodityCategoryService::class);
        $uniqueSlug = $service->generateUniqueSlug('wheat-grains');

        // Collision with soft-deleted record forces incremental suffix
        $this->assertEquals('wheat-grains-2', $uniqueSlug);
    }

    public function test_admin_can_view_category_detail(): void
    {
        $cat = CommodityCategory::create([
            'name' => 'Detailed Cat',
            'slug' => 'detailed-cat',
            'description' => 'Long description text',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson("/api/admin/commodity-categories/{$cat->id}")
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'id' => $cat->id,
                    'name' => 'Detailed Cat',
                    'slug' => 'detailed-cat',
                    'description' => 'Long description text',
                    'creator' => [
                        'id' => $this->admin->id,
                        'name' => $this->admin->name,
                    ],
                ],
            ]);
    }

    public function test_view_detail_returns_404_for_non_existent_or_soft_deleted_id(): void
    {
        $this->withToken($this->adminToken)
            ->getJson('/api/admin/commodity-categories/99999')
            ->assertStatus(404);

        $cat = CommodityCategory::create(['name' => 'Deleted', 'slug' => 'deleted-cat']);
        $cat->delete();

        $this->withToken($this->adminToken)
            ->getJson("/api/admin/commodity-categories/{$cat->id}")
            ->assertStatus(404);
    }

    public function test_admin_can_update_category_and_omitted_slug_retains_existing_slug(): void
    {
        $cat = CommodityCategory::create([
            'name' => 'Old Name',
            'slug' => 'original-slug',
            'sort_order' => 1,
            'status' => true,
        ]);

        $response = $this->withToken($this->adminToken)
            ->putJson("/api/admin/commodity-categories/{$cat->id}", [
                'name' => 'New Name Only',
            ])
            ->assertStatus(200);

        $this->assertEquals('New Name Only', $response->json('data.name'));
        $this->assertEquals('original-slug', $response->json('data.slug'));

        $this->assertDatabaseHas('commodity_categories', [
            'id' => $cat->id,
            'name' => 'New Name Only',
            'slug' => 'original-slug',
            'updated_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_update_slug_with_unique_ignore_current_record(): void
    {
        $cat = CommodityCategory::create([
            'name' => 'My Category',
            'slug' => 'my-category',
        ]);

        $response = $this->withToken($this->adminToken)
            ->putJson("/api/admin/commodity-categories/{$cat->id}", [
                'name' => 'My Category',
                'slug' => 'my-category', // same slug is ignored for this ID
            ])
            ->assertStatus(200);

        $this->assertEquals('my-category', $response->json('data.slug'));
    }

    public function test_admin_can_update_status(): void
    {
        $cat = CommodityCategory::create([
            'name' => 'Status Cat',
            'slug' => 'status-cat',
            'status' => true,
        ]);

        $response = $this->withToken($this->adminToken)
            ->patchJson("/api/admin/commodity-categories/{$cat->id}/status", [
                'status' => false,
            ])
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'id' => $cat->id,
                    'status' => false,
                ],
            ]);

        $this->assertFalse((bool) $cat->fresh()->status);
    }

    public function test_bulk_status_update_works(): void
    {
        $cat1 = CommodityCategory::create(['name' => 'Cat 1', 'slug' => 'cat-1', 'status' => true]);
        $cat2 = CommodityCategory::create(['name' => 'Cat 2', 'slug' => 'cat-2', 'status' => true]);

        $response = $this->withToken($this->adminToken)
            ->patchJson('/api/admin/commodity-categories/bulk-status', [
                'ids' => [$cat1->id, $cat2->id],
                'status' => false,
            ])
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'updated_count' => 2,
                ],
            ]);

        $this->assertFalse((bool) $cat1->fresh()->status);
        $this->assertFalse((bool) $cat2->fresh()->status);
    }

    public function test_single_delete_soft_deletes_record_and_invalidates_cache(): void
    {
        $cat = CommodityCategory::create([
            'name' => 'To Delete',
            'slug' => 'to-delete',
            'status' => true,
        ]);

        // Prime cache
        Cache::put(CommodityCategoryService::CACHE_KEY_OPTIONS, ['cached_data']);

        $response = $this->withToken($this->adminToken)
            ->deleteJson("/api/admin/commodity-categories/{$cat->id}")
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Commodity category deleted successfully.',
            ]);

        $this->assertSoftDeleted('commodity_categories', [
            'id' => $cat->id,
        ]);

        $this->assertNull(Cache::get(CommodityCategoryService::CACHE_KEY_OPTIONS));
    }

    public function test_delete_bulk_delete_is_not_registered(): void
    {
        // DELETE /api/admin/commodity-categories/bulk-delete must not exist as DELETE method
        $response = $this->withToken($this->adminToken)
            ->deleteJson('/api/admin/commodity-categories/bulk-delete');

        // Either 404 or 405 Method Not Allowed
        $this->assertContains($response->status(), [404, 405]);
    }

    public function test_post_bulk_delete_works_atomically(): void
    {
        $cat1 = CommodityCategory::create(['name' => 'Bulk 1', 'slug' => 'bulk-1']);
        $cat2 = CommodityCategory::create(['name' => 'Bulk 2', 'slug' => 'bulk-2']);
        $cat3 = CommodityCategory::create(['name' => 'Bulk 3', 'slug' => 'bulk-3']);

        // Prime cache
        Cache::put(CommodityCategoryService::CACHE_KEY_OPTIONS, ['cached_data']);

        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodity-categories/bulk-delete', [
                'ids' => [$cat1->id, $cat2->id, $cat3->id],
            ])
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Commodity categories deleted successfully.',
                'data' => [
                    'deleted_count' => 3,
                ],
            ]);

        $this->assertSoftDeleted('commodity_categories', ['id' => $cat1->id]);
        $this->assertSoftDeleted('commodity_categories', ['id' => $cat2->id]);
        $this->assertSoftDeleted('commodity_categories', ['id' => $cat3->id]);

        $this->assertNull(Cache::get(CommodityCategoryService::CACHE_KEY_OPTIONS));
    }

    public function test_bulk_delete_validation_handles_duplicates_and_invalid_ids(): void
    {
        $cat = CommodityCategory::create(['name' => 'Cat Bulk', 'slug' => 'cat-bulk']);

        // Duplicate IDs validation error
        $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodity-categories/bulk-delete', [
                'ids' => [$cat->id, $cat->id],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ids.0', 'ids.1']);

        // Invalid non-existent ID
        $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodity-categories/bulk-delete', [
                'ids' => [99999],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ids.0']);
    }

    public function test_exact_seeded_category_names_are_correct(): void
    {
        $this->seed(CommodityCategorySeeder::class);

        $expectedCategories = [
            'grains' => 'Grains',
            'pulses' => 'Pulses',
            'oilseeds' => 'Oilseeds',
            'edible-oils' => 'Edible Oils',
            'spices' => 'Spices',
            'dry-fruits-nuts' => 'Dry Fruits / Nuts',
            'sugar-sweeteners' => 'Sugar & Sweeteners',
            'feed-by-products' => 'Feed / By-products',
            'international-benchmarks' => 'International Benchmarks',
        ];

        foreach ($expectedCategories as $slug => $name) {
            $this->assertDatabaseHas('commodity_categories', [
                'slug' => $slug,
                'name' => $name,
            ]);
        }
    }
}
