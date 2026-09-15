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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
            'name' => 'Grains',
            'slug' => 'grains',
            'status' => true,
        ]);

        $this->categoryPulses = CommodityCategory::create([
            'name' => 'Pulses',
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
            'name' => 'Wheat',
            'slug' => 'wheat',
            'code' => 'WHEAT',
            'unit' => 'QUINTAL',
            'sort_order' => 1,
            'status' => true,
        ]);
        Commodity::create([
            'commodity_category_id' => $this->categoryPulses->id,
            'name' => 'Chana',
            'slug' => 'chana',
            'code' => 'CHANA',
            'unit' => 'QUINTAL',
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
                            'category' => ['id', 'name', 'slug'],
                            'name',
                            'slug',
                            'code',
                            'unit',
                            'image',
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
        $this->assertEquals('WHEAT', $response->json('data.items.0.code'));
        $this->assertEquals('QUINTAL', $response->json('data.items.0.unit'));
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

    public function test_search_by_name_code_and_category_filtering(): void
    {
        Commodity::create(['commodity_category_id' => $this->categoryGrains->id, 'name' => 'Wheat', 'slug' => 'wheat', 'code' => 'WHEAT', 'unit' => 'QUINTAL', 'status' => true]);
        Commodity::create(['commodity_category_id' => $this->categoryGrains->id, 'name' => 'Rice', 'slug' => 'rice', 'code' => 'RICE', 'unit' => 'QUINTAL', 'status' => true]);
        Commodity::create(['commodity_category_id' => $this->categoryPulses->id, 'name' => 'Chana', 'slug' => 'chana', 'code' => 'CHANA', 'unit' => 'QUINTAL', 'status' => true]);

        // Search Name
        $resName = $this->withToken($this->adminToken)->getJson('/api/admin/commodities?search=wheat')->assertStatus(200);
        $this->assertCount(1, $resName->json('data.items'));
        $this->assertEquals('Wheat', $resName->json('data.items.0.name'));

        // Search Code
        $resCode = $this->withToken($this->adminToken)->getJson('/api/admin/commodities?search=CHANA')->assertStatus(200);
        $this->assertCount(1, $resCode->json('data.items'));
        $this->assertEquals('Chana', $resCode->json('data.items.0.name'));

        // Search Slug
        $resSlug = $this->withToken($this->adminToken)->getJson('/api/admin/commodities?search=rice')->assertStatus(200);
        $this->assertCount(1, $resSlug->json('data.items'));
        $this->assertEquals('Rice', $resSlug->json('data.items.0.name'));

        // Filter Category
        $resCat = $this->withToken($this->adminToken)->getJson("/api/admin/commodities?commodity_category_id={$this->categoryGrains->id}")->assertStatus(200);
        $this->assertCount(2, $resCat->json('data.items'));
    }

    public function test_admin_can_create_commodity_with_auto_slug_code_unit_and_normalization(): void
    {
        $payload = [
            'commodity_category_id' => $this->categoryGrains->id,
            'name' => '  Barley Malt  ',
            'code' => '  barley_malt  ',
            'unit' => '  quintal  ',
            'description' => '  Barley grains for brewing  ',
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
                    'name' => 'Barley Malt',
                    'slug' => 'barley-malt',
                    'code' => 'BARLEY_MALT',
                    'unit' => 'QUINTAL',
                    'sort_order' => 5,
                    'status' => true,
                ],
            ]);

        $this->assertDatabaseHas('commodities', [
            'name' => 'Barley Malt',
            'slug' => 'barley-malt',
            'code' => 'BARLEY_MALT',
            'unit' => 'QUINTAL',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_store_validation_requires_code_and_unit(): void
    {
        $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodities', [
                'commodity_category_id' => $this->categoryGrains->id,
                'name' => 'Mustard',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'unit']);
    }

    public function test_store_rejects_duplicate_code(): void
    {
        Commodity::create([
            'commodity_category_id' => $this->categoryGrains->id,
            'name' => 'Wheat',
            'slug' => 'wheat',
            'code' => 'WHEAT',
            'unit' => 'QUINTAL',
        ]);

        $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodities', [
                'commodity_category_id' => $this->categoryGrains->id,
                'name' => 'Another Wheat',
                'code' => 'wheat', // lowercase should normalize to WHEAT and collide
                'unit' => 'QUINTAL',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_store_image_optional_and_valid_upload(): void
    {
        Storage::fake('public');

        $imageFile = UploadedFile::fake()->image('commodity.png', 300, 300);

        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodities', [
                'commodity_category_id' => $this->categoryGrains->id,
                'name' => 'Soybean Meal',
                'code' => 'SOY_MEAL',
                'unit' => 'MT',
                'image' => $imageFile,
            ])
            ->assertStatus(201);

        $data = $response->json('data');
        $this->assertNotEmpty($data['image']);
        $commodity = Commodity::where('code', 'SOY_MEAL')->first();
        $this->assertNotNull($commodity);
        $this->assertNotNull($commodity->image);
        Storage::disk('public')->assertExists($commodity->image);
    }

    public function test_store_rejects_invalid_and_oversized_image(): void
    {
        Storage::fake('public');

        // Invalid MIME type (pdf)
        $invalidFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodities', [
                'commodity_category_id' => $this->categoryGrains->id,
                'name' => 'Doc Comm',
                'code' => 'DOC_COMM',
                'unit' => 'KG',
                'image' => $invalidFile,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image']);

        // Oversized image (> 2MB)
        $oversizedFile = UploadedFile::fake()->image('large.jpg')->size(3000);

        $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodities', [
                'commodity_category_id' => $this->categoryGrains->id,
                'name' => 'Large Comm',
                'code' => 'LARGE_COMM',
                'unit' => 'KG',
                'image' => $oversizedFile,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    public function test_store_rejects_inactive_or_soft_deleted_category(): void
    {
        $inactiveCat = CommodityCategory::create(['name' => 'Inactive Cat', 'slug' => 'inactive-cat', 'status' => false]);
        $deletedCat = CommodityCategory::create(['name' => 'Deleted Cat', 'slug' => 'deleted-cat', 'status' => true]);
        $deletedCat->delete();

        // Inactive category rejected on store
        $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodities', [
                'commodity_category_id' => $inactiveCat->id,
                'name' => 'Test Item',
                'code' => 'TEST_ITEM_1',
                'unit' => 'KG',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['commodity_category_id']);

        // Deleted category rejected
        $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodities', [
                'commodity_category_id' => $deletedCat->id,
                'name' => 'Test Item 2',
                'code' => 'TEST_ITEM_2',
                'unit' => 'KG',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['commodity_category_id']);
    }

    public function test_duplicate_slug_and_soft_deleted_slug_reservations_on_store(): void
    {
        $existing = Commodity::create([
            'commodity_category_id' => $this->categoryGrains->id,
            'name' => 'Maize',
            'slug' => 'maize',
            'code' => 'MAIZE',
            'unit' => 'QUINTAL',
        ]);

        // Duplicate active slug
        $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodities', [
                'commodity_category_id' => $this->categoryGrains->id,
                'name' => 'Other Maize',
                'slug' => 'maize',
                'code' => 'OTHER_MAIZE',
                'unit' => 'QUINTAL',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);

        $existing->delete(); // Soft delete

        // Soft-deleted slug remains reserved
        $this->withToken($this->adminToken)
            ->postJson('/api/admin/commodities', [
                'commodity_category_id' => $this->categoryGrains->id,
                'name' => 'New Maize',
                'slug' => 'maize',
                'code' => 'NEW_MAIZE',
                'unit' => 'QUINTAL',
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
            'name' => 'Detailed Wheat',
            'slug' => 'detailed-wheat',
            'code' => 'DETAILED_WHEAT',
            'unit' => 'QUINTAL',
            'description' => 'Full specification description text.',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson("/api/admin/commodities/{$commodity->id}")
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'id' => $commodity->id,
                    'name' => 'Detailed Wheat',
                    'slug' => 'detailed-wheat',
                    'code' => 'DETAILED_WHEAT',
                    'unit' => 'QUINTAL',
                    'description' => 'Full specification description text.',
                    'category' => [
                        'id' => $this->categoryGrains->id,
                        'name' => 'Grains',
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

        $commodity = Commodity::create([
            'commodity_category_id' => $this->categoryGrains->id,
            'name' => 'Deleted Item',
            'slug' => 'deleted-item',
            'code' => 'DELETED_ITEM',
            'unit' => 'KG',
        ]);
        $commodity->delete();

        $this->withToken($this->adminToken)->getJson("/api/admin/commodities/{$commodity->id}")->assertStatus(404);
    }

    public function test_update_commodity_category_and_omitted_slug(): void
    {
        $commodity = Commodity::create([
            'commodity_category_id' => $this->categoryGrains->id,
            'name' => 'Original Grain Item',
            'slug' => 'original-grain-item',
            'code' => 'ORIG_GRAIN',
            'unit' => 'QUINTAL',
        ]);

        // 1. Update without slug retains existing slug
        $response1 = $this->withToken($this->adminToken)
            ->putJson("/api/admin/commodities/{$commodity->id}", [
                'name' => 'Updated Grain Item',
                'code' => 'orig_grain_upd',
                'unit' => 'kg',
            ])
            ->assertStatus(200);
        $this->assertEquals('Updated Grain Item', $response1->json('data.name'));
        $this->assertEquals('original-grain-item', $response1->json('data.slug'));
        $this->assertEquals('ORIG_GRAIN_UPD', $response1->json('data.code'));
        $this->assertEquals('KG', $response1->json('data.unit'));

        // 2. Reassign to valid active category
        $response2 = $this->withToken($this->adminToken)
            ->putJson("/api/admin/commodities/{$commodity->id}", [
                'commodity_category_id' => $this->categoryPulses->id,
            ])
            ->assertStatus(200);
        $this->assertEquals($this->categoryPulses->id, $response2->json('data.commodity_category_id'));
    }

    public function test_update_retains_existing_image_when_omitted(): void
    {
        Storage::fake('public');

        $initialPath = 'commodities/existing.png';
        Storage::disk('public')->put($initialPath, 'fake-image-data');

        $commodity = Commodity::create([
            'commodity_category_id' => $this->categoryGrains->id,
            'name' => 'Keep Image Item',
            'slug' => 'keep-image-item',
            'code' => 'KEEP_IMG',
            'unit' => 'QUINTAL',
            'image' => $initialPath,
        ]);

        $this->withToken($this->adminToken)
            ->putJson("/api/admin/commodities/{$commodity->id}", [
                'name' => 'Keep Image Item Renamed',
            ])
            ->assertStatus(200);

        $commodity->refresh();
        $this->assertEquals($initialPath, $commodity->image);
        Storage::disk('public')->assertExists($initialPath);
    }

    public function test_update_replaces_image_and_deletes_old_image(): void
    {
        Storage::fake('public');

        $oldPath = 'commodities/old-image.png';
        Storage::disk('public')->put($oldPath, 'old-image-data');

        $commodity = Commodity::create([
            'commodity_category_id' => $this->categoryGrains->id,
            'name' => 'Replace Image Item',
            'slug' => 'replace-image-item',
            'code' => 'REPLACE_IMG',
            'unit' => 'QUINTAL',
            'image' => $oldPath,
        ]);

        $newImage = UploadedFile::fake()->image('new-image.webp', 400, 400);

        $response = $this->withToken($this->adminToken)
            ->putJson("/api/admin/commodities/{$commodity->id}", [
                'image' => $newImage,
            ])
            ->assertStatus(200);

        $commodity->refresh();
        $this->assertNotEquals($oldPath, $commodity->image);
        $this->assertNotNull($commodity->image);
        Storage::disk('public')->assertExists($commodity->image);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_update_rejects_duplicate_code_for_other_commodity(): void
    {
        Commodity::create([
            'commodity_category_id' => $this->categoryGrains->id,
            'name' => 'Wheat',
            'slug' => 'wheat',
            'code' => 'WHEAT',
            'unit' => 'QUINTAL',
        ]);

        $c2 = Commodity::create([
            'commodity_category_id' => $this->categoryPulses->id,
            'name' => 'Chana',
            'slug' => 'chana',
            'code' => 'CHANA',
            'unit' => 'QUINTAL',
        ]);

        // Attempt to rename C2 code to WHEAT
        $this->withToken($this->adminToken)
            ->putJson("/api/admin/commodities/{$c2->id}", [
                'code' => 'WHEAT',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        // Updating self with same code should succeed (ignore self)
        $this->withToken($this->adminToken)
            ->putJson("/api/admin/commodities/{$c2->id}", [
                'code' => 'CHANA',
                'name' => 'Chana Renamed',
            ])
            ->assertStatus(200);
    }

    public function test_editing_ordinary_fields_under_existing_inactive_parent_is_allowed(): void
    {
        $inactiveCat = CommodityCategory::create(['name' => 'Old Inactive Cat', 'slug' => 'old-inactive-cat', 'status' => false]);
        $commodity = Commodity::create([
            'commodity_category_id' => $inactiveCat->id,
            'name' => 'Existing Item',
            'slug' => 'existing-item',
            'code' => 'EXISTING_ITEM',
            'unit' => 'QUINTAL',
        ]);

        // Editing name without changing category should succeed
        $response = $this->withToken($this->adminToken)
            ->putJson("/api/admin/commodities/{$commodity->id}", [
                'name' => 'Existing Item Updated',
            ])
            ->assertStatus(200);

        $this->assertEquals('Existing Item Updated', $response->json('data.name'));

        // Attempting to change to ANOTHER inactive category is rejected
        $anotherInactiveCat = CommodityCategory::create(['name' => 'Another Inactive', 'slug' => 'another-inactive', 'status' => false]);
        $this->withToken($this->adminToken)
            ->putJson("/api/admin/commodities/{$commodity->id}", [
                'commodity_category_id' => $anotherInactiveCat->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['commodity_category_id']);
    }

    public function test_status_update_and_bulk_status_update(): void
    {
        $c1 = Commodity::create(['commodity_category_id' => $this->categoryGrains->id, 'name' => 'C1', 'slug' => 'c1', 'code' => 'C1_CODE', 'unit' => 'KG', 'status' => true]);
        $c2 = Commodity::create(['commodity_category_id' => $this->categoryPulses->id, 'name' => 'C2', 'slug' => 'c2', 'code' => 'C2_CODE', 'unit' => 'KG', 'status' => true]);

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
        $c1 = Commodity::create(['commodity_category_id' => $this->categoryGrains->id, 'name' => 'Del 1', 'slug' => 'del-1', 'code' => 'DEL_1', 'unit' => 'KG']);
        $c2 = Commodity::create(['commodity_category_id' => $this->categoryPulses->id, 'name' => 'Del 2', 'slug' => 'del-2', 'code' => 'DEL_2', 'unit' => 'KG']);

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
            'wheat' => ['name' => 'Wheat', 'code' => 'WHEAT', 'unit' => 'QUINTAL'],
            'chana' => ['name' => 'Chana', 'code' => 'CHANA', 'unit' => 'QUINTAL'],
            'soybean' => ['name' => 'Soybean', 'code' => 'SOYBEAN', 'unit' => 'QUINTAL'],
            'mustard-oil' => ['name' => 'Mustard Oil', 'code' => 'MUSTARD_OIL', 'unit' => '10_KG'],
            'jeera-cumin' => ['name' => 'Jeera (Cumin)', 'code' => 'JEERA', 'unit' => 'QUINTAL'],
            'turmeric' => ['name' => 'Turmeric', 'code' => 'TURMERIC', 'unit' => 'QUINTAL'],
            'sugar' => ['name' => 'Sugar', 'code' => 'SUGAR', 'unit' => 'QUINTAL'],
            'almonds' => ['name' => 'Almonds', 'code' => 'ALMONDS', 'unit' => 'KG'],
        ];

        foreach ($expected as $slug => $data) {
            $this->assertDatabaseHas('commodities', [
                'slug' => $slug,
                'name' => $data['name'],
                'code' => $data['code'],
                'unit' => $data['unit'],
            ]);
        }
    }

    public function test_options_contains_expected_fields_and_excludes_inactive_category(): void
    {
        $activeCat = CommodityCategory::create(['name' => 'Active Cat', 'slug' => 'active-cat-opt', 'status' => true]);
        $inactiveCat = CommodityCategory::create(['name' => 'Inactive Cat', 'slug' => 'inactive-cat-opt', 'status' => false]);

        $commActive = Commodity::create([
            'commodity_category_id' => $activeCat->id,
            'name' => 'Active Comm',
            'slug' => 'active-comm',
            'code' => 'ACTIVE_COMM',
            'unit' => 'BAG',
            'status' => true,
        ]);
        $commUnderInactive = Commodity::create([
            'commodity_category_id' => $inactiveCat->id,
            'name' => 'Hidden Comm',
            'slug' => 'hidden-comm',
            'code' => 'HIDDEN_COMM',
            'unit' => 'BAG',
            'status' => true,
        ]);

        // Options call
        $response = $this->withToken($this->adminToken)->getJson('/api/admin/commodities/options')->assertStatus(200);
        $items = $response->json('data');

        $slugs = array_column($items, 'slug');
        $this->assertContains('active-comm', $slugs);
        $this->assertNotContains('hidden-comm', $slugs);

        // Verify options payload structure
        $activeItem = collect($items)->firstWhere('slug', 'active-comm');
        $this->assertEquals('ACTIVE_COMM', $activeItem['code']);
        $this->assertEquals('BAG', $activeItem['unit']);

        // Reactivating category restores commodity in options
        $inactiveCat->update(['status' => true]);
        app(CommodityCategoryService::class)->clearCache((int) $inactiveCat->id);

        $responseReactivated = $this->withToken($this->adminToken)->getJson('/api/admin/commodities/options')->assertStatus(200);
        $slugsReactivated = array_column($responseReactivated->json('data'), 'slug');
        $this->assertContains('hidden-comm', $slugsReactivated);
    }

    public function test_category_with_commodities_cannot_be_deleted_returning_409(): void
    {
        $cat = CommodityCategory::create(['name' => 'Protected Cat', 'slug' => 'protected-cat', 'status' => true]);
        $commodity = Commodity::create([
            'commodity_category_id' => $cat->id,
            'name' => 'Child Comm',
            'slug' => 'child-comm',
            'code' => 'CHILD_COMM',
            'unit' => 'KG',
        ]);

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
        $catFree = CommodityCategory::create(['name' => 'Free Cat', 'slug' => 'free-cat', 'status' => true]);

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
