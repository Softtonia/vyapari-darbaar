<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use App\Models\CommoditySubcategory;
use App\Models\CommodityVariety;
use App\Models\User;
use App\Services\CommodityCategoryService;
use App\Services\CommodityService;
use App\Services\CommoditySubcategoryService;
use App\Services\CommodityVarietyService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CommodityVarietyManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected string $adminToken;

    protected CommodityCategory $categoryGrains;

    protected CommodityCategory $categoryPulses;

    protected Commodity $commodityWheat;

    protected Commodity $commodityChana;

    protected CommoditySubcategory $subcatMillingWheat;

    protected CommoditySubcategory $subcatDesiChana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->admin = Admin::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'name' => 'Super Admin',
            'email' => 'admin.varieties.crud@example.com',
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

        $this->commodityWheat = Commodity::create([
            'commodity_category_id' => $this->categoryGrains->id,
            'name' => 'Wheat',
            'slug' => 'wheat',
            'status' => true,
        ]);

        $this->commodityChana = Commodity::create([
            'commodity_category_id' => $this->categoryPulses->id,
            'name' => 'Chana',
            'slug' => 'chana',
            'status' => true,
        ]);

        $this->subcatMillingWheat = CommoditySubcategory::create([
            'commodity_id' => $this->commodityWheat->id,
            'name' => 'Milling Wheat',
            'slug' => 'milling-wheat',
            'status' => true,
        ]);

        $this->subcatDesiChana = CommoditySubcategory::create([
            'commodity_id' => $this->commodityChana->id,
            'name' => 'Desi Chana',
            'slug' => 'desi-chana',
            'status' => true,
        ]);

        Cache::flush();
    }

    public function test_unauthenticated_requests_are_blocked(): void
    {
        $this->getJson('/api/admin/commodity-varieties')->assertStatus(401);
        $this->getJson('/api/admin/commodity-varieties/options')->assertStatus(401);
        $this->postJson('/api/admin/commodity-varieties', [])->assertStatus(401);
        $this->getJson('/api/admin/commodity-varieties/1')->assertStatus(401);
        $this->putJson('/api/admin/commodity-varieties/1', [])->assertStatus(401);
        $this->patchJson('/api/admin/commodity-varieties/1/status', [])->assertStatus(401);
        $this->deleteJson('/api/admin/commodity-varieties/1')->assertStatus(401);
        $this->postJson('/api/admin/commodity-varieties/bulk-delete', [])->assertStatus(401);
        $this->patchJson('/api/admin/commodity-varieties/bulk-status', [])->assertStatus(401);
    }

    public function test_user_token_cannot_access_variety_endpoints(): void
    {
        $user = User::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'name' => 'John Doe',
            'username' => 'john.doe',
            'phone_number' => '+919999999999',
            'email' => 'user@example.com',
            'password' => Hash::make('UserPass@12345'),
            'status' => 'active',
        ]);

        $token = $user->createToken('user-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/commodity-varieties')
            ->assertStatus(403);
    }

    public function test_list_varieties_with_pagination_and_default_sorting(): void
    {
        CommodityVariety::create([
            'commodity_id' => $this->commodityWheat->id,
            'name' => 'Variety B',
            'slug' => 'variety-b',
            'sort_order' => 2,
            'status' => true,
        ]);

        CommodityVariety::create([
            'commodity_id' => $this->commodityWheat->id,
            'name' => 'Variety A',
            'slug' => 'variety-a',
            'sort_order' => 1,
            'status' => true,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/admin/commodity-varieties')
            ->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'items' => [
                        '*' => [
                            'id',
                            'commodity_id',
                            'commodity_subcategory_id',
                            'commodity',
                            'subcategory',
                            'name',
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

        $items = $response->json('data.items');
        $this->assertCount(2, $items);
        $this->assertEquals('Variety A', $items[0]['name']);
        $this->assertEquals('Variety B', $items[1]['name']);
    }

    public function test_list_varieties_search_by_name_and_slug(): void
    {
        CommodityVariety::create([
            'commodity_id' => $this->commodityWheat->id,
            'name' => 'Sharbati Gold',
            'slug' => 'sharbati-gold',
            'status' => true,
        ]);

        // Name search
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/admin/commodity-varieties?search=Gold')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items');

        // Slug search
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/admin/commodity-varieties?search=sharbati')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_list_varieties_filter_by_category_commodity_subcategory_status(): void
    {
        $v1 = CommodityVariety::create([
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => $this->subcatMillingWheat->id,
            'name' => 'HD-2967',
            'slug' => 'hd-2967',
            'status' => true,
        ]);

        $v2 = CommodityVariety::create([
            'commodity_id' => $this->commodityChana->id,
            'commodity_subcategory_id' => $this->subcatDesiChana->id,
            'name' => 'JG-11',
            'slug' => 'jg-11',
            'status' => false,
        ]);

        // Category filter
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/admin/commodity-varieties?commodity_category_id={$this->categoryGrains->id}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $v1->id);

        // Commodity filter
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/admin/commodity-varieties?commodity_id={$this->commodityChana->id}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $v2->id);

        // Subcategory filter
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/admin/commodity-varieties?commodity_subcategory_id={$this->subcatMillingWheat->id}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $v1->id);

        // Status filter
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/admin/commodity-varieties?status=0')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $v2->id);
    }

    public function test_list_mismatched_relational_filters_return_422(): void
    {
        // Category Pulses + Commodity Wheat
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/admin/commodity-varieties?commodity_category_id={$this->categoryPulses->id}&commodity_id={$this->commodityWheat->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['commodity_id']);

        // Commodity Wheat + Subcategory Desi Chana
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/admin/commodity-varieties?commodity_id={$this->commodityWheat->id}&commodity_subcategory_id={$this->subcatDesiChana->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['commodity_subcategory_id']);

        // Category Grains + Subcategory Desi Chana
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/admin/commodity-varieties?commodity_category_id={$this->categoryGrains->id}&commodity_subcategory_id={$this->subcatDesiChana->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['commodity_subcategory_id']);
    }

    public function test_create_direct_commodity_variety_successfully(): void
    {
        $payload = [
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => null,
            'name' => 'HD-2967',
            'slug' => 'hd-2967',
            'description' => 'High yield variety',
            'sort_order' => 1,
            'status' => true,
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/admin/commodity-varieties', $payload)
            ->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', 'HD-2967')
            ->assertJsonPath('data.slug', 'hd-2967')
            ->assertJsonPath('data.commodity_subcategory_id', null);

        $this->assertDatabaseHas('commodity_varieties', [
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => null,
            'slug' => 'hd-2967',
        ]);
    }

    public function test_create_subcategory_variety_successfully(): void
    {
        $payload = [
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => $this->subcatMillingWheat->id,
            'name' => 'Lokwan Grade A',
            'slug' => 'lokwan-grade-a',
            'status' => true,
        ];

        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/admin/commodity-varieties', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.commodity_subcategory_id', $this->subcatMillingWheat->id);

        $this->assertDatabaseHas('commodity_varieties', [
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => $this->subcatMillingWheat->id,
            'slug' => 'lokwan-grade-a',
        ]);
    }

    public function test_create_variety_slug_auto_generation_and_collision_suffix(): void
    {
        CommodityVariety::create([
            'commodity_id' => $this->commodityWheat->id,
            'name' => 'Lokwan Wheat',
            'slug' => 'lokwan-wheat',
            'status' => true,
        ]);

        $payload = [
            'commodity_id' => $this->commodityWheat->id,
            'name' => 'Lokwan Wheat',
            'slug' => null,
        ];

        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/admin/commodity-varieties', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'lokwan-wheat-2');
    }

    public function test_create_variety_same_slug_in_different_commodities_allowed(): void
    {
        CommodityVariety::create([
            'commodity_id' => $this->commodityWheat->id,
            'name' => 'Premium Quality',
            'slug' => 'premium-quality',
            'status' => true,
        ]);

        $payload = [
            'commodity_id' => $this->commodityChana->id,
            'name' => 'Premium Quality',
            'slug' => 'premium-quality',
            'status' => true,
        ];

        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/admin/commodity-varieties', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'premium-quality');
    }

    public function test_create_variety_duplicate_slug_same_commodity_rejected(): void
    {
        CommodityVariety::create([
            'commodity_id' => $this->commodityWheat->id,
            'name' => 'HD-2967',
            'slug' => 'hd-2967',
            'status' => true,
        ]);

        $payload = [
            'commodity_id' => $this->commodityWheat->id,
            'name' => 'Different Name',
            'slug' => 'hd-2967',
        ];

        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/admin/commodity-varieties', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_create_variety_soft_deleted_slug_in_same_commodity_rejected(): void
    {
        $variety = CommodityVariety::create([
            'commodity_id' => $this->commodityWheat->id,
            'name' => 'Deleted Variety',
            'slug' => 'deleted-slug',
            'status' => true,
        ]);
        $variety->delete();

        $payload = [
            'commodity_id' => $this->commodityWheat->id,
            'name' => 'New Variety',
            'slug' => 'deleted-slug',
        ];

        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/admin/commodity-varieties', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_create_variety_with_inactive_or_cross_commodity_parents_rejected(): void
    {
        // 1. Cross-commodity subcategory assignment
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/admin/commodity-varieties', [
                'commodity_id' => $this->commodityWheat->id,
                'commodity_subcategory_id' => $this->subcatDesiChana->id,
                'name' => 'Cross Test',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['commodity_subcategory_id']);

        // 2. Inactive Commodity
        $this->commodityWheat->update(['status' => false]);
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/admin/commodity-varieties', [
                'commodity_id' => $this->commodityWheat->id,
                'name' => 'Inactive Comm Test',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['commodity_id']);
        $this->commodityWheat->update(['status' => true]);

        // 3. Commodity under inactive Category
        $this->categoryGrains->update(['status' => false]);
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/admin/commodity-varieties', [
                'commodity_id' => $this->commodityWheat->id,
                'name' => 'Inactive Cat Test',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['commodity_id']);
        $this->categoryGrains->update(['status' => true]);

        // 4. Inactive Subcategory
        $this->subcatMillingWheat->update(['status' => false]);
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/admin/commodity-varieties', [
                'commodity_id' => $this->commodityWheat->id,
                'commodity_subcategory_id' => $this->subcatMillingWheat->id,
                'name' => 'Inactive Subcat Test',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['commodity_subcategory_id']);
    }

    public function test_show_variety_detail_returns_expected_structure(): void
    {
        $variety = CommodityVariety::create([
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => $this->subcatMillingWheat->id,
            'name' => 'HD-2967',
            'slug' => 'hd-2967',
            'description' => 'High quality',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
            'status' => true,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/admin/commodity-varieties/{$variety->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $variety->id)
            ->assertJsonPath('data.name', 'HD-2967')
            ->assertJsonPath('data.commodity.slug', 'wheat')
            ->assertJsonPath('data.subcategory.slug', 'milling-wheat')
            ->assertJsonPath('data.creator.email', $this->admin->email);
    }

    public function test_update_variety_fields_and_slug_handling(): void
    {
        $variety = CommodityVariety::create([
            'commodity_id' => $this->commodityWheat->id,
            'name' => 'Old Name',
            'slug' => 'old-slug',
            'status' => true,
        ]);

        // Omitted slug retains existing slug
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson("/api/admin/commodity-varieties/{$variety->id}", [
                'name' => 'New Name',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.slug', 'old-slug');

        // Explicit slug updates slug
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson("/api/admin/commodity-varieties/{$variety->id}", [
                'slug' => 'new-explicit-slug',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.slug', 'new-explicit-slug');
    }

    public function test_update_omitted_vs_explicit_null_subcategory(): void
    {
        $variety = CommodityVariety::create([
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => $this->subcatMillingWheat->id,
            'name' => 'Variety Test',
            'slug' => 'variety-test',
            'status' => true,
        ]);

        // Omitted subcategory: retains existing subcategory
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson("/api/admin/commodity-varieties/{$variety->id}", [
                'name' => 'Variety Test Updated',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.commodity_subcategory_id', $this->subcatMillingWheat->id);

        // Explicit null: detaches subcategory
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson("/api/admin/commodity-varieties/{$variety->id}", [
                'commodity_subcategory_id' => null,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.commodity_subcategory_id', null);

        $this->assertDatabaseHas('commodity_varieties', [
            'id' => $variety->id,
            'commodity_subcategory_id' => null,
        ]);
    }

    public function test_commodity_reassignment_rules_and_slug_collision(): void
    {
        $variety = CommodityVariety::create([
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => $this->subcatMillingWheat->id,
            'name' => 'Super Variety',
            'slug' => 'super-variety',
            'status' => true,
        ]);

        // 1. Changing commodity without explicit subcategory decision returns 422
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson("/api/admin/commodity-varieties/{$variety->id}", [
                'commodity_id' => $this->commodityChana->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['commodity_subcategory_id']);

        // 2. Changing commodity with wrong subcategory returns 422
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson("/api/admin/commodity-varieties/{$variety->id}", [
                'commodity_id' => $this->commodityChana->id,
                'commodity_subcategory_id' => $this->subcatMillingWheat->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['commodity_subcategory_id']);

        // 3. Changing commodity with target slug collision returns 422
        CommodityVariety::create([
            'commodity_id' => $this->commodityChana->id,
            'name' => 'Existing Chana Variety',
            'slug' => 'super-variety',
            'status' => true,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson("/api/admin/commodity-varieties/{$variety->id}", [
                'commodity_id' => $this->commodityChana->id,
                'commodity_subcategory_id' => null,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);

        // Record must remain completely unchanged
        $this->assertDatabaseHas('commodity_varieties', [
            'id' => $variety->id,
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => $this->subcatMillingWheat->id,
            'slug' => 'super-variety',
        ]);

        // 4. Changing commodity with explicit null and unique slug succeeds
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson("/api/admin/commodity-varieties/{$variety->id}", [
                'commodity_id' => $this->commodityChana->id,
                'commodity_subcategory_id' => null,
                'slug' => 'super-variety-chana',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.commodity_id', $this->commodityChana->id)
            ->assertJsonPath('data.commodity_subcategory_id', null);

        // 5. Changing commodity with valid matching subcategory succeeds
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson("/api/admin/commodity-varieties/{$variety->id}", [
                'commodity_id' => $this->commodityChana->id,
                'commodity_subcategory_id' => $this->subcatDesiChana->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.commodity_subcategory_id', $this->subcatDesiChana->id);
    }

    public function test_ordinary_updates_under_inactive_current_parent_allowed(): void
    {
        $variety = CommodityVariety::create([
            'commodity_id' => $this->commodityWheat->id,
            'name' => 'Editable Variety',
            'slug' => 'editable-variety',
            'status' => true,
        ]);

        // Deactivate parent commodity
        $this->commodityWheat->update(['status' => false]);

        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson("/api/admin/commodity-varieties/{$variety->id}", [
                'description' => 'Updated Description under inactive parent',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.description', 'Updated Description under inactive parent');
    }

    public function test_update_variety_status_and_bulk_status(): void
    {
        $v1 = CommodityVariety::create([
            'commodity_id' => $this->commodityWheat->id,
            'name' => 'Variety 1',
            'slug' => 'v1',
            'status' => true,
        ]);

        $v2 = CommodityVariety::create([
            'commodity_id' => $this->commodityWheat->id,
            'name' => 'Variety 2',
            'slug' => 'v2',
            'status' => true,
        ]);

        // Single status
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->patchJson("/api/admin/commodity-varieties/{$v1->id}/status", [
                'status' => false,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', false);

        // Bulk status
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->patchJson('/api/admin/commodity-varieties/bulk-status', [
                'ids' => [$v1->id, $v2->id],
                'status' => true,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.updated_count', 2);

        $this->assertTrue((bool) $v1->fresh()->status);
        $this->assertTrue((bool) $v2->fresh()->status);
    }

    public function test_single_and_bulk_delete_varieties(): void
    {
        $v1 = CommodityVariety::create([
            'commodity_id' => $this->commodityWheat->id,
            'name' => 'Variety 1',
            'slug' => 'v1',
            'status' => true,
        ]);

        $v2 = CommodityVariety::create([
            'commodity_id' => $this->commodityWheat->id,
            'name' => 'Variety 2',
            'slug' => 'v2',
            'status' => true,
        ]);

        // Single delete
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->deleteJson("/api/admin/commodity-varieties/{$v1->id}")
            ->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->assertSoftDeleted('commodity_varieties', ['id' => $v1->id]);

        // Bulk delete
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/admin/commodity-varieties/bulk-delete', [
                'ids' => [$v2->id],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.deleted_count', 1);

        $this->assertSoftDeleted('commodity_varieties', ['id' => $v2->id]);
    }

    public function test_options_effective_visibility_and_hierarchy_suppression(): void
    {
        $directVariety = CommodityVariety::create([
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => null,
            'name' => 'Direct Wheat Variety',
            'slug' => 'direct-wheat',
            'status' => true,
        ]);

        $subcatVariety = CommodityVariety::create([
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => $this->subcatMillingWheat->id,
            'name' => 'Subcat Wheat Variety',
            'slug' => 'subcat-wheat',
            'status' => true,
        ]);

        // 1. Both active varieties visible
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/admin/commodity-varieties/options')
            ->assertStatus(200);

        $this->assertCount(2, $response->json('data'));

        // 2. Inactive Subcategory suppresses ONLY subcategory variety
        Cache::flush();
        $this->subcatMillingWheat->update(['status' => false]);
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/admin/commodity-varieties/options')
            ->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($directVariety->id, $response->json('data.0.id'));
        $this->subcatMillingWheat->update(['status' => true]);

        // 3. Inactive Commodity suppresses BOTH varieties
        Cache::flush();
        $this->commodityWheat->update(['status' => false]);
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/admin/commodity-varieties/options')
            ->assertStatus(200);

        $this->assertCount(0, $response->json('data'));
        $this->commodityWheat->update(['status' => true]);

        // 4. Inactive Category suppresses BOTH varieties
        Cache::flush();
        $this->categoryGrains->update(['status' => false]);
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/admin/commodity-varieties/options')
            ->assertStatus(200);

        $this->assertCount(0, $response->json('data'));
        $this->categoryGrains->update(['status' => true]);

        // 5. Options filter by commodity
        Cache::flush();
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/admin/commodity-varieties/options?commodity_id={$this->commodityWheat->id}")
            ->assertStatus(200);
        $this->assertCount(2, $response->json('data'));

        // 6. Options filter by subcategory
        Cache::flush();
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/admin/commodity-varieties/options?commodity_subcategory_id={$this->subcatMillingWheat->id}")
            ->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($subcatVariety->id, $response->json('data.0.id'));

        // 7. Options combined mismatched filter returns 422
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/admin/commodity-varieties/options?commodity_id={$this->commodityChana->id}&commodity_subcategory_id={$this->subcatMillingWheat->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['commodity_subcategory_id']);
    }

    public function test_subcategory_delete_protection_and_reparenting_protection_by_varieties(): void
    {
        $variety = CommodityVariety::create([
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => $this->subcatMillingWheat->id,
            'name' => 'Milling Variety',
            'slug' => 'milling-variety',
            'status' => true,
        ]);

        // 1. Single delete blocked (HTTP 409)
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->deleteJson("/api/admin/commodity-subcategories/{$this->subcatMillingWheat->id}")
            ->assertStatus(409)
            ->assertJsonPath('error', 'COMMODITY_SUBCATEGORY_IN_USE');

        // 2. Inactive variety still blocks subcategory delete
        $variety->update(['status' => false]);
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->deleteJson("/api/admin/commodity-subcategories/{$this->subcatMillingWheat->id}")
            ->assertStatus(409);

        // 3. Bulk delete blocked
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/admin/commodity-subcategories/bulk-delete', [
                'ids' => [$this->subcatMillingWheat->id],
            ])
            ->assertStatus(409)
            ->assertJsonPath('data.blocked_ids.0', $this->subcatMillingWheat->id);

        // 4. Subcategory reparenting blocked when varieties exist (HTTP 409)
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson("/api/admin/commodity-subcategories/{$this->subcatMillingWheat->id}", [
                'commodity_id' => $this->commodityChana->id,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error', 'COMMODITY_SUBCATEGORY_IN_USE');

        $this->assertEquals($this->commodityWheat->id, $this->subcatMillingWheat->fresh()->commodity_id);

        // 5. Soft-deleted variety does NOT block deletion or reparenting
        $variety->delete();

        // Reparenting succeeds
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson("/api/admin/commodity-subcategories/{$this->subcatMillingWheat->id}", [
                'commodity_id' => $this->commodityChana->id,
            ])
            ->assertStatus(200);

        $this->assertEquals($this->commodityChana->id, $this->subcatMillingWheat->fresh()->commodity_id);

        // Single delete succeeds
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->deleteJson("/api/admin/commodity-subcategories/{$this->subcatMillingWheat->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('commodity_subcategories', ['id' => $this->subcatMillingWheat->id]);
    }

    public function test_commodity_delete_protection_by_direct_and_indirect_varieties(): void
    {
        // Direct variety under Wheat
        $directVariety = CommodityVariety::create([
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => null,
            'name' => 'Direct Wheat Variety',
            'slug' => 'direct-wheat-var',
            'status' => true,
        ]);

        // Delete commodity subcategory so only direct variety remains
        $this->subcatMillingWheat->delete();

        // 1. Single delete blocked by direct variety (HTTP 409)
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->deleteJson("/api/admin/commodities/{$this->commodityWheat->id}")
            ->assertStatus(409)
            ->assertJsonPath('error', 'COMMODITY_IN_USE');

        // 2. Bulk delete blocked
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/admin/commodities/bulk-delete', [
                'ids' => [$this->commodityWheat->id, $this->commodityChana->id],
            ])
            ->assertStatus(409)
            ->assertJsonPath('data.blocked_ids', [$this->commodityWheat->id, $this->commodityChana->id]);

        // Soft-delete direct variety and chana subcategory
        $directVariety->delete();
        $this->subcatDesiChana->delete();

        // Single delete succeeds
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->deleteJson("/api/admin/commodities/{$this->commodityWheat->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('commodities', ['id' => $this->commodityWheat->id]);
    }

    public function test_cross_module_cache_invalidation(): void
    {
        $variety = CommodityVariety::create([
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => $this->subcatMillingWheat->id,
            'name' => 'Cached Variety',
            'slug' => 'cached-variety',
            'status' => true,
        ]);

        /** @var CommodityVarietyService $service */
        $service = app(CommodityVarietyService::class);

        // Warm up caches
        $service->getOptions();
        $service->getOptions($this->commodityWheat->id);
        $service->getOptions(null, $this->subcatMillingWheat->id);

        $this->assertTrue(Cache::has(CommodityVarietyService::CACHE_KEY_OPTIONS_ALL));
        $this->assertTrue(Cache::has(CommodityVarietyService::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$this->commodityWheat->id));
        $this->assertTrue(Cache::has(CommodityVarietyService::CACHE_KEY_OPTIONS_SUBCATEGORY_PREFIX.$this->subcatMillingWheat->id));

        // 1. Commodity status change invalidates variety cache
        /** @var CommodityService $commService */
        $commService = app(CommodityService::class);
        $commService->updateStatus($this->commodityWheat, false);

        $this->assertFalse(Cache::has(CommodityVarietyService::CACHE_KEY_OPTIONS_ALL));
        $this->assertFalse(Cache::has(CommodityVarietyService::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$this->commodityWheat->id));
        $this->assertFalse(Cache::has(CommodityVarietyService::CACHE_KEY_OPTIONS_SUBCATEGORY_PREFIX.$this->subcatMillingWheat->id));

        // Warm up caches again
        $service->getOptions();
        $service->getOptions($this->commodityWheat->id);
        $service->getOptions(null, $this->subcatMillingWheat->id);

        // 2. Subcategory status change invalidates variety cache
        /** @var CommoditySubcategoryService $subcatService */
        $subcatService = app(CommoditySubcategoryService::class);
        $subcatService->updateStatus($this->subcatMillingWheat, false);

        $this->assertFalse(Cache::has(CommodityVarietyService::CACHE_KEY_OPTIONS_ALL));
        $this->assertFalse(Cache::has(CommodityVarietyService::CACHE_KEY_OPTIONS_SUBCATEGORY_PREFIX.$this->subcatMillingWheat->id));

        // Warm up caches again
        $service->getOptions();
        $service->getOptions($this->commodityWheat->id);
        $service->getOptions(null, $this->subcatMillingWheat->id);

        // 3. Category status change invalidates variety cache
        /** @var CommodityCategoryService $catService */
        $catService = app(CommodityCategoryService::class);
        $catService->updateStatus($this->categoryGrains, false);

        $this->assertFalse(Cache::has(CommodityVarietyService::CACHE_KEY_OPTIONS_ALL));
        $this->assertFalse(Cache::has(CommodityVarietyService::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$this->commodityWheat->id));
        $this->assertFalse(Cache::has(CommodityVarietyService::CACHE_KEY_OPTIONS_SUBCATEGORY_PREFIX.$this->subcatMillingWheat->id));
    }
}
