<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use App\Models\CommodityGrade;
use App\Models\CommoditySubcategory;
use App\Models\CommodityVariety;
use App\Models\Role;
use App\Models\User;
use App\Services\CommodityCategoryService;
use App\Services\CommodityGradeService;
use App\Services\CommodityService;
use App\Services\CommoditySubcategoryService;
use App\Services\CommodityVarietyService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CommodityGradeManagementTest extends TestCase
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

    protected CommodityVariety $varietyDirectWheat;

    protected CommodityVariety $varietyMillingWheatHD;

    protected CommodityVariety $varietyDesiChanaAvrodhi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->admin = Admin::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'name' => 'Super Admin',
            'email' => 'admin.grades.crud@example.com',
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

        $this->varietyDirectWheat = CommodityVariety::create([
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => null,
            'name' => 'Direct Wheat Variety',
            'slug' => 'direct-wheat-variety',
            'status' => true,
        ]);

        $this->varietyMillingWheatHD = CommodityVariety::create([
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => $this->subcatMillingWheat->id,
            'name' => 'HD-2967',
            'slug' => 'hd-2967',
            'status' => true,
        ]);

        $this->varietyDesiChanaAvrodhi = CommodityVariety::create([
            'commodity_id' => $this->commodityChana->id,
            'commodity_subcategory_id' => $this->subcatDesiChana->id,
            'name' => 'Avrodhi',
            'slug' => 'avrodhi',
            'status' => true,
        ]);
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->adminToken}",
            'Accept' => 'application/json',
        ];
    }

    /**
     * Test 1: Unauthenticated request is rejected.
     */
    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/admin/commodity-grades');
        $response->assertStatus(401);
    }

    /**
     * Test 2: Regular user or unauthorized admin cannot access admin grade endpoints.
     */
    public function test_regular_user_cannot_access_admin_grade_endpoints(): void
    {
        $user = User::create([
            'first_name' => 'Regular',
            'last_name' => 'User',
            'name' => 'Regular User',
            'username' => 'regular.user',
            'phone_number' => '9876543210',
            'email' => 'regular.user@example.com',
            'password' => Hash::make('Secret123'),
            'status' => 'active',
        ]);
        $userToken = $user->createToken('user-token')->plainTextToken;

        $response = $this->getJson('/api/admin/commodity-grades', [
            'Authorization' => "Bearer {$userToken}",
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test 3: Create all 4 valid grade hierarchy forms.
     */
    public function test_can_create_all_four_valid_grade_hierarchy_types(): void
    {
        // 1. Direct Commodity Grade
        $res1 = $this->postJson('/api/admin/commodity-grades', [
            'commodity_id' => $this->commodityWheat->id,
            'name' => 'Grade Direct Wheat',
            'description' => 'Wheat direct grade description',
        ], $this->authHeaders());

        $res1->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.commodity_subcategory_id', null)
            ->assertJsonPath('data.commodity_variety_id', null)
            ->assertJsonPath('data.slug', 'grade-direct-wheat');

        // 2. Subcategory Grade
        $res2 = $this->postJson('/api/admin/commodity-grades', [
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => $this->subcatMillingWheat->id,
            'name' => 'Grade Milling Wheat',
        ], $this->authHeaders());

        $res2->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.commodity_subcategory_id', $this->subcatMillingWheat->id)
            ->assertJsonPath('data.commodity_variety_id', null);

        // 3. Direct Variety Grade
        $res3 = $this->postJson('/api/admin/commodity-grades', [
            'commodity_id' => $this->commodityWheat->id,
            'commodity_variety_id' => $this->varietyDirectWheat->id,
            'name' => 'Grade Direct Variety',
        ], $this->authHeaders());

        $res3->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.commodity_subcategory_id', null)
            ->assertJsonPath('data.commodity_variety_id', $this->varietyDirectWheat->id);

        // 4. Subcategory-Linked Variety Grade
        $res4 = $this->postJson('/api/admin/commodity-grades', [
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => $this->subcatMillingWheat->id,
            'commodity_variety_id' => $this->varietyMillingWheatHD->id,
            'name' => 'Grade HD-2967 Premium',
        ], $this->authHeaders());

        $res4->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.commodity_subcategory_id', $this->subcatMillingWheat->id)
            ->assertJsonPath('data.commodity_variety_id', $this->varietyMillingWheatHD->id);
    }

    /**
     * Test 4: Creation validation rejects inconsistent hierarchies and inactive parents.
     */
    public function test_create_validation_rejects_inconsistent_hierarchies_and_inactive_parents(): void
    {
        // Direct variety + non-null subcategory -> 422
        $res1 = $this->postJson('/api/admin/commodity-grades', [
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => $this->subcatMillingWheat->id,
            'commodity_variety_id' => $this->varietyDirectWheat->id,
            'name' => 'Invalid Combination 1',
        ], $this->authHeaders());

        $res1->assertStatus(422)
            ->assertJsonValidationErrors(['commodity_subcategory_id']);

        // Subcategory variety + null subcategory -> 422
        $res2 = $this->postJson('/api/admin/commodity-grades', [
            'commodity_id' => $this->commodityWheat->id,
            'commodity_variety_id' => $this->varietyMillingWheatHD->id,
            'name' => 'Invalid Combination 2',
        ], $this->authHeaders());

        $res2->assertStatus(422)
            ->assertJsonValidationErrors(['commodity_subcategory_id']);

        // Inactive parent category
        $this->categoryGrains->update(['status' => false]);
        $resInactive = $this->postJson('/api/admin/commodity-grades', [
            'commodity_id' => $this->commodityWheat->id,
            'name' => 'Grade Inactive Parent',
        ], $this->authHeaders());

        $resInactive->assertStatus(422)
            ->assertJsonValidationErrors(['commodity_id']);
    }

    /**
     * Test 5: List endpoint with filtering, search, and contradictory filter rejection.
     */
    public function test_list_and_filter_consistency(): void
    {
        CommodityGrade::create([
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => $this->subcatMillingWheat->id,
            'commodity_variety_id' => $this->varietyMillingWheatHD->id,
            'name' => 'Wheat Super Grade',
            'slug' => 'wheat-super-grade',
            'status' => true,
        ]);

        // Search by name
        $resSearch = $this->getJson('/api/admin/commodity-grades?search=Super', $this->authHeaders());
        $resSearch->assertStatus(200)
            ->assertJsonCount(1, 'data.items');

        // Valid filter
        $res = $this->getJson("/api/admin/commodity-grades?commodity_id={$this->commodityWheat->id}&commodity_subcategory_id={$this->subcatMillingWheat->id}", $this->authHeaders());
        $res->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'data.items');

        // Contradictory filters (Wheat commodity with Desi Chana subcategory) -> 422
        $resInvalid = $this->getJson("/api/admin/commodity-grades?commodity_id={$this->commodityWheat->id}&commodity_subcategory_id={$this->subcatDesiChana->id}", $this->authHeaders());
        $resInvalid->assertStatus(422)
            ->assertJsonValidationErrors(['commodity_subcategory_id']);
    }

    /**
     * Test 6: Options endpoint caching, precedence, and suppression of inactive parents.
     */
    public function test_options_endpoint_caching_and_suppression(): void
    {
        Cache::flush();

        $grade = CommodityGrade::create([
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => $this->subcatMillingWheat->id,
            'commodity_variety_id' => $this->varietyMillingWheatHD->id,
            'name' => 'HD Option Grade',
            'slug' => 'hd-option-grade',
            'status' => true,
        ]);

        // Variety precedence
        $res = $this->getJson("/api/admin/commodity-grades/options?commodity_id={$this->commodityWheat->id}&commodity_subcategory_id={$this->subcatMillingWheat->id}&commodity_variety_id={$this->varietyMillingWheatHD->id}", $this->authHeaders());
        $res->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->assertTrue(Cache::has(CommodityGradeService::CACHE_KEY_OPTIONS_VARIETY_PREFIX.$this->varietyMillingWheatHD->id));

        // Subcategory precedence
        $resSub = $this->getJson("/api/admin/commodity-grades/options?commodity_id={$this->commodityWheat->id}&commodity_subcategory_id={$this->subcatMillingWheat->id}", $this->authHeaders());
        $resSub->assertStatus(200);
        $this->assertTrue(Cache::has(CommodityGradeService::CACHE_KEY_OPTIONS_SUBCATEGORY_PREFIX.$this->subcatMillingWheat->id));

        // Deactivate variety directly in DB -> suppressed in options
        $this->varietyMillingWheatHD->update(['status' => false]);
        Cache::flush();
        $resSuppressed = $this->getJson("/api/admin/commodity-grades/options?commodity_id={$this->commodityWheat->id}&commodity_subcategory_id={$this->subcatMillingWheat->id}&commodity_variety_id={$this->varietyMillingWheatHD->id}", $this->authHeaders());
        $resSuppressed->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // Reactivate variety -> restored
        $this->varietyMillingWheatHD->update(['status' => true]);
        Cache::flush();
        $resRestored = $this->getJson("/api/admin/commodity-grades/options?commodity_id={$this->commodityWheat->id}&commodity_subcategory_id={$this->subcatMillingWheat->id}&commodity_variety_id={$this->varietyMillingWheatHD->id}", $this->authHeaders());
        $resRestored->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    /**
     * Test 7: UPDATE: Key Present != Relationship Changed and ordinary edit under inactive parent.
     */
    public function test_update_same_relationship_values_does_not_trigger_reassignment_validation(): void
    {
        $grade = CommodityGrade::create([
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => $this->subcatMillingWheat->id,
            'commodity_variety_id' => $this->varietyMillingWheatHD->id,
            'name' => 'Existing Grade',
            'slug' => 'existing-grade',
            'status' => true,
        ]);

        // Deactivate parent commodity directly in DB
        $this->commodityWheat->update(['status' => false]);

        // Submit same relationship IDs with updated description
        $res = $this->putJson("/api/admin/commodity-grades/{$grade->id}", [
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => $this->subcatMillingWheat->id,
            'commodity_variety_id' => $this->varietyMillingWheatHD->id,
            'description' => 'Updated Description While Parent Inactive',
        ], $this->authHeaders());

        $res->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.description', 'Updated Description While Parent Inactive');
    }

    /**
     * Test 8: Omitted vs Explicit Null semantics on update.
     */
    public function test_omitted_vs_explicit_null_semantics(): void
    {
        $grade = CommodityGrade::create([
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => $this->subcatMillingWheat->id,
            'commodity_variety_id' => $this->varietyMillingWheatHD->id,
            'name' => 'Hierarchy Grade',
            'slug' => 'hierarchy-grade',
            'status' => true,
        ]);

        // 1. Omitted subcategory and variety -> retains both
        $res1 = $this->putJson("/api/admin/commodity-grades/{$grade->id}", [
            'description' => 'Retain Relationships',
        ], $this->authHeaders());

        $res1->assertStatus(200)
            ->assertJsonPath('data.commodity_subcategory_id', $this->subcatMillingWheat->id)
            ->assertJsonPath('data.commodity_variety_id', $this->varietyMillingWheatHD->id);

        // 2. Explicit null on variety -> detaches variety, retains subcategory
        $res2 = $this->putJson("/api/admin/commodity-grades/{$grade->id}", [
            'commodity_variety_id' => null,
        ], $this->authHeaders());

        $res2->assertStatus(200)
            ->assertJsonPath('data.commodity_subcategory_id', $this->subcatMillingWheat->id)
            ->assertJsonPath('data.commodity_variety_id', null);

        // 3. Explicit null on subcategory -> detaches subcategory
        $res3 = $this->putJson("/api/admin/commodity-grades/{$grade->id}", [
            'commodity_subcategory_id' => null,
        ], $this->authHeaders());

        $res3->assertStatus(200)
            ->assertJsonPath('data.commodity_subcategory_id', null)
            ->assertJsonPath('data.commodity_variety_id', null);
    }

    /**
     * Test 9: Subcategory detach with existing subcategory variety returns 422.
     */
    public function test_subcategory_detach_with_existing_variety_fails_validation(): void
    {
        $grade = CommodityGrade::create([
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => $this->subcatMillingWheat->id,
            'commodity_variety_id' => $this->varietyMillingWheatHD->id,
            'name' => 'HD Subcat Grade',
            'slug' => 'hd-subcat-grade',
            'status' => true,
        ]);

        // Detaching subcategory while variety HD-2967 is still attached -> 422
        $res = $this->putJson("/api/admin/commodity-grades/{$grade->id}", [
            'commodity_subcategory_id' => null,
        ], $this->authHeaders());

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['commodity_subcategory_id']);

        // Explicitly detaching both succeeds
        $resValid = $this->putJson("/api/admin/commodity-grades/{$grade->id}", [
            'commodity_subcategory_id' => null,
            'commodity_variety_id' => null,
        ], $this->authHeaders());

        $resValid->assertStatus(200)
            ->assertJsonPath('data.commodity_subcategory_id', null)
            ->assertJsonPath('data.commodity_variety_id', null);
    }

    /**
     * Test 10: Commodity move requires explicit subcategory and variety decisions.
     */
    public function test_commodity_move_requires_explicit_child_decisions(): void
    {
        $grade = CommodityGrade::create([
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => $this->subcatMillingWheat->id,
            'commodity_variety_id' => $this->varietyMillingWheatHD->id,
            'name' => 'Move Test Grade',
            'slug' => 'move-test-grade',
            'status' => true,
        ]);

        // Move commodity without subcat/variety decisions -> 422
        $res = $this->putJson("/api/admin/commodity-grades/{$grade->id}", [
            'commodity_id' => $this->commodityChana->id,
        ], $this->authHeaders());

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['commodity_subcategory_id', 'commodity_variety_id']);

        // Ensure failed move leaves original unchanged
        $this->assertEquals($this->commodityWheat->id, $grade->fresh()->commodity_id);

        // Move with explicit nulls succeeds
        $resNull = $this->putJson("/api/admin/commodity-grades/{$grade->id}", [
            'commodity_id' => $this->commodityChana->id,
            'commodity_subcategory_id' => null,
            'commodity_variety_id' => null,
        ], $this->authHeaders());

        $resNull->assertStatus(200)
            ->assertJsonPath('data.commodity_id', $this->commodityChana->id)
            ->assertJsonPath('data.commodity_subcategory_id', null)
            ->assertJsonPath('data.commodity_variety_id', null);

        // Move with complete valid new hierarchy succeeds
        $resNewHierarchy = $this->putJson("/api/admin/commodity-grades/{$grade->id}", [
            'commodity_id' => $this->commodityChana->id,
            'commodity_subcategory_id' => $this->subcatDesiChana->id,
            'commodity_variety_id' => $this->varietyDesiChanaAvrodhi->id,
        ], $this->authHeaders());

        $resNewHierarchy->assertStatus(200)
            ->assertJsonPath('data.commodity_id', $this->commodityChana->id)
            ->assertJsonPath('data.commodity_subcategory_id', $this->subcatDesiChana->id)
            ->assertJsonPath('data.commodity_variety_id', $this->varietyDesiChanaAvrodhi->id);
    }

    /**
     * Test 11: Slug uniqueness check across active and soft-deleted records.
     */
    public function test_slug_uniqueness_including_soft_deletes_when_moving_commodity(): void
    {
        // Create soft deleted grade under Chana
        $deletedGrade = CommodityGrade::create([
            'commodity_id' => $this->commodityChana->id,
            'name' => 'Special Chana Grade',
            'slug' => 'special-grade',
            'status' => true,
        ]);
        $deletedGrade->delete();

        // Create grade under Wheat
        $gradeWheat = CommodityGrade::create([
            'commodity_id' => $this->commodityWheat->id,
            'name' => 'Special Wheat Grade',
            'slug' => 'special-grade',
            'status' => true,
        ]);

        // Attempting to move gradeWheat to Chana with same slug 'special-grade' -> 422
        $res = $this->putJson("/api/admin/commodity-grades/{$gradeWheat->id}", [
            'commodity_id' => $this->commodityChana->id,
            'commodity_subcategory_id' => null,
            'commodity_variety_id' => null,
            'slug' => 'special-grade',
        ], $this->authHeaders());

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    /**
     * Test 12: Single and bulk status updates.
     */
    public function test_single_and_bulk_status_updates(): void
    {
        $g1 = CommodityGrade::create([
            'commodity_id' => $this->commodityWheat->id,
            'name' => 'Grade 1',
            'slug' => 'grade-1',
            'status' => true,
        ]);

        $g2 = CommodityGrade::create([
            'commodity_id' => $this->commodityWheat->id,
            'name' => 'Grade 2',
            'slug' => 'grade-2',
            'status' => true,
        ]);

        // Single status update
        $res1 = $this->patchJson("/api/admin/commodity-grades/{$g1->id}/status", [
            'status' => false,
        ], $this->authHeaders());

        $res1->assertStatus(200)
            ->assertJsonPath('data.status', false);

        // Bulk status update
        $resBulk = $this->patchJson('/api/admin/commodity-grades/bulk-status', [
            'ids' => [$g1->id, $g2->id],
            'status' => false,
        ], $this->authHeaders());

        $resBulk->assertStatus(200)
            ->assertJsonPath('data.updated_count', 2);
    }

    /**
     * Test 13: Single delete and atomic bulk delete.
     */
    public function test_single_delete_and_atomic_bulk_delete(): void
    {
        $g1 = CommodityGrade::create([
            'commodity_id' => $this->commodityWheat->id,
            'name' => 'Delete Grade 1',
            'slug' => 'delete-grade-1',
            'status' => true,
        ]);

        $g2 = CommodityGrade::create([
            'commodity_id' => $this->commodityWheat->id,
            'name' => 'Delete Grade 2',
            'slug' => 'delete-grade-2',
            'status' => true,
        ]);

        // Single delete
        $res = $this->deleteJson("/api/admin/commodity-grades/{$g1->id}", [], $this->authHeaders());
        $res->assertStatus(200);
        $this->assertSoftDeleted('commodity_grades', ['id' => $g1->id]);

        // Bulk delete with one missing ID -> returns 422
        $resInvalid = $this->postJson('/api/admin/commodity-grades/bulk-delete', [
            'ids' => [$g2->id, 99999],
        ], $this->authHeaders());

        $resInvalid->assertStatus(422)
            ->assertJsonValidationErrors(['ids']);

        // Bulk delete with valid ID
        $resBulk = $this->postJson('/api/admin/commodity-grades/bulk-delete', [
            'ids' => [$g2->id],
        ], $this->authHeaders());

        $resBulk->assertStatus(200)
            ->assertJsonPath('data.deleted_count', 1);

        $this->assertSoftDeleted('commodity_grades', ['id' => $g2->id]);
    }

    /**
     * Test 14: Inactive Grade blocks Variety delete and reparenting.
     */
    public function test_inactive_grade_blocks_variety_delete_and_reparenting(): void
    {
        $grade = CommodityGrade::create([
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => $this->subcatMillingWheat->id,
            'commodity_variety_id' => $this->varietyMillingWheatHD->id,
            'name' => 'Inactive Grade Blocker',
            'slug' => 'inactive-grade-blocker',
            'status' => false, // Inactive still blocks!
        ]);

        // Attempting to delete variety HD-2967 -> 409 COMMODITY_VARIETY_IN_USE
        $resDel = $this->deleteJson("/api/admin/commodity-varieties/{$this->varietyMillingWheatHD->id}", [], $this->authHeaders());
        $resDel->assertStatus(409)
            ->assertJsonPath('error', 'COMMODITY_VARIETY_IN_USE');

        // Attempting to move variety HD-2967 to Chana -> 409 COMMODITY_VARIETY_IN_USE
        $resMove = $this->putJson("/api/admin/commodity-varieties/{$this->varietyMillingWheatHD->id}", [
            'commodity_id' => $this->commodityChana->id,
            'commodity_subcategory_id' => $this->subcatDesiChana->id,
        ], $this->authHeaders());

        $resMove->assertStatus(409)
            ->assertJsonPath('error', 'COMMODITY_VARIETY_IN_USE');

        // Soft delete the grade -> variety delete now succeeds
        $grade->delete();

        $resDelSuccess = $this->deleteJson("/api/admin/commodity-varieties/{$this->varietyMillingWheatHD->id}", [], $this->authHeaders());
        $resDelSuccess->assertStatus(200);
    }

    /**
     * Test 15: Inactive Grade blocks Subcategory delete and reparenting.
     */
    public function test_inactive_grade_blocks_subcategory_delete_and_reparenting(): void
    {
        $grade = CommodityGrade::create([
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => $this->subcatMillingWheat->id,
            'commodity_variety_id' => null, // Direct subcategory grade
            'name' => 'Subcat Blocker Grade',
            'slug' => 'subcat-blocker-grade',
            'status' => false,
        ]);

        // Attempting to delete subcategory -> 409 COMMODITY_SUBCATEGORY_IN_USE
        $resDel = $this->deleteJson("/api/admin/commodity-subcategories/{$this->subcatMillingWheat->id}", [], $this->authHeaders());
        $resDel->assertStatus(409)
            ->assertJsonPath('error', 'COMMODITY_SUBCATEGORY_IN_USE');

        // Attempting to move subcategory -> 409 COMMODITY_SUBCATEGORY_IN_USE
        $resMove = $this->putJson("/api/admin/commodity-subcategories/{$this->subcatMillingWheat->id}", [
            'commodity_id' => $this->commodityChana->id,
        ], $this->authHeaders());

        $resMove->assertStatus(409)
            ->assertJsonPath('error', 'COMMODITY_SUBCATEGORY_IN_USE');
    }

    /**
     * Test 16: Direct Grade blocks Commodity delete.
     */
    public function test_direct_grade_blocks_commodity_delete(): void
    {
        $grade = CommodityGrade::create([
            'commodity_id' => $this->commodityWheat->id,
            'name' => 'Direct Wheat Grade Blocker',
            'slug' => 'direct-wheat-blocker',
            'status' => true,
        ]);

        $resDel = $this->deleteJson("/api/admin/commodities/{$this->commodityWheat->id}", [], $this->authHeaders());
        $resDel->assertStatus(409)
            ->assertJsonPath('error', 'COMMODITY_IN_USE');
    }

    /**
     * Test 17: Bulk delete dependency protections for Variety, Subcategory, and Commodity.
     */
    public function test_bulk_delete_dependency_merging(): void
    {
        $grade = CommodityGrade::create([
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => $this->subcatMillingWheat->id,
            'commodity_variety_id' => $this->varietyMillingWheatHD->id,
            'name' => 'Bulk Blocker Grade',
            'slug' => 'bulk-blocker-grade',
            'status' => true,
        ]);

        // Variety bulk delete blocked
        $resVar = $this->postJson('/api/admin/commodity-varieties/bulk-delete', [
            'ids' => [$this->varietyMillingWheatHD->id],
        ], $this->authHeaders());

        $resVar->assertStatus(409)
            ->assertJsonPath('data.blocked_ids.0', $this->varietyMillingWheatHD->id);

        // Subcategory bulk delete blocked
        $resSub = $this->postJson('/api/admin/commodity-subcategories/bulk-delete', [
            'ids' => [$this->subcatMillingWheat->id],
        ], $this->authHeaders());

        $resSub->assertStatus(409)
            ->assertJsonPath('data.blocked_ids.0', $this->subcatMillingWheat->id);

        // Commodity bulk delete blocked
        $resComm = $this->postJson('/api/admin/commodities/bulk-delete', [
            'ids' => [$this->commodityWheat->id],
        ], $this->authHeaders());

        $resComm->assertStatus(409)
            ->assertJsonPath('data.blocked_ids.0', $this->commodityWheat->id);
    }

    /**
     * Test 18: Cross-module cache invalidation when parent entities change status and failed validation does not purge cache.
     */
    public function test_cross_module_cache_invalidation_and_failed_validation_isolation(): void
    {
        Cache::flush();

        $grade = CommodityGrade::create([
            'commodity_id' => $this->commodityWheat->id,
            'commodity_subcategory_id' => $this->subcatMillingWheat->id,
            'commodity_variety_id' => $this->varietyMillingWheatHD->id,
            'name' => 'Cached Grade',
            'slug' => 'cached-grade',
            'status' => true,
        ]);

        // Populate options cache
        $this->getJson("/api/admin/commodity-grades/options?commodity_id={$this->commodityWheat->id}", $this->authHeaders());
        $this->assertTrue(Cache::has(CommodityGradeService::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$this->commodityWheat->id));

        // Failed validation does NOT purge cache
        $this->putJson("/api/admin/commodity-grades/{$grade->id}", [
            'commodity_subcategory_id' => null, // Invalid since variety requires subcategory
        ], $this->authHeaders())->assertStatus(422);

        $this->assertTrue(Cache::has(CommodityGradeService::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$this->commodityWheat->id));

        // Update Category status -> clears Grade caches
        $this->patchJson("/api/admin/commodity-categories/{$this->categoryGrains->id}/status", ['status' => false], $this->authHeaders());
        $this->assertFalse(Cache::has(CommodityGradeService::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$this->commodityWheat->id));

        // Re-populate options cache
        $this->getJson("/api/admin/commodity-grades/options?commodity_id={$this->commodityWheat->id}", $this->authHeaders());
        $this->assertTrue(Cache::has(CommodityGradeService::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$this->commodityWheat->id));

        // Update Commodity status -> clears Grade caches
        $this->patchJson("/api/admin/commodities/{$this->commodityWheat->id}/status", ['status' => false], $this->authHeaders());
        $this->assertFalse(Cache::has(CommodityGradeService::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$this->commodityWheat->id));
    }
}
