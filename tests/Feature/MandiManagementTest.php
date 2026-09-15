<?php

namespace Tests\Feature;

use App\Enums\MarketType;
use App\Models\Admin;
use App\Models\District;
use App\Models\Mandi;
use App\Models\State;
use App\Models\User;
use App\Services\MandiService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MandiManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected string $adminToken;

    protected State $stateMH;

    protected State $stateMP;

    protected District $districtNagpur;

    protected District $districtAkola;

    protected District $districtIndore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->admin = Admin::create([
            'first_name' => 'Mandi',
            'last_name' => 'Admin',
            'name' => 'Mandi Admin',
            'email' => 'admin.mandi.crud@example.com',
            'password' => Hash::make('AdminPass@12345'),
            'status' => 'active',
        ]);

        $this->admin->assignRole('admin');
        $this->adminToken = $this->admin->createToken('admin-token')->plainTextToken;

        $this->stateMH = State::create(['name' => 'Maharashtra', 'slug' => 'maharashtra', 'code' => 'MH', 'status' => true]);
        $this->stateMP = State::create(['name' => 'Madhya Pradesh', 'slug' => 'madhya-pradesh', 'code' => 'MP', 'status' => true]);

        $this->districtNagpur = District::create(['state_id' => $this->stateMH->id, 'name' => 'Nagpur', 'slug' => 'nagpur', 'code' => 'NGP', 'status' => true]);
        $this->districtAkola = District::create(['state_id' => $this->stateMH->id, 'name' => 'Akola', 'slug' => 'akola', 'code' => 'AKL', 'status' => true]);
        $this->districtIndore = District::create(['state_id' => $this->stateMP->id, 'name' => 'Indore', 'slug' => 'indore', 'code' => 'IND', 'status' => true]);

        Cache::forget(MandiService::CACHE_KEY_OPTIONS_DISTRICT_PREFIX.$this->districtNagpur->id);
        Cache::forget(MandiService::CACHE_KEY_OPTIONS_DISTRICT_PREFIX.$this->districtAkola->id);
        Cache::forget(MandiService::CACHE_KEY_OPTIONS_DISTRICT_PREFIX.$this->districtIndore->id);
        Cache::forget(MandiService::CACHE_KEY_OPTIONS_STATE_PREFIX.$this->stateMH->id);
        Cache::forget(MandiService::CACHE_KEY_OPTIONS_STATE_PREFIX.$this->stateMP->id);
    }

    public function test_unauthenticated_requests_are_blocked(): void
    {
        $this->getJson('/api/admin/mandis')->assertStatus(401);
        $this->getJson('/api/admin/mandis/options')->assertStatus(401);
        $this->postJson('/api/admin/mandis', [])->assertStatus(401);
        $this->getJson('/api/admin/mandis/1')->assertStatus(401);
        $this->putJson('/api/admin/mandis/1', [])->assertStatus(401);
        $this->patchJson('/api/admin/mandis/1/status', [])->assertStatus(401);
        $this->patchJson('/api/admin/mandis/bulk-status', [])->assertStatus(401);
        $this->deleteJson('/api/admin/mandis/1')->assertStatus(401);
        $this->postJson('/api/admin/mandis/bulk-delete', [])->assertStatus(401);
    }

    public function test_user_token_is_forbidden_from_admin_mandis(): void
    {
        $user = User::create([
            'first_name' => 'Regular',
            'last_name' => 'User',
            'name' => 'Regular User',
            'phone_number' => '+919876543210',
            'username' => 'reg.mandi.user',
            'email' => 'trader.mandi@example.com',
            'password' => Hash::make('Secret123#'),
            'status' => 'active',
        ]);

        $userToken = $user->createToken('user-token')->plainTextToken;

        $this->withToken($userToken)
            ->getJson('/api/admin/mandis')
            ->assertStatus(403);
    }

    public function test_mandis_table_does_not_contain_state_id_column(): void
    {
        $this->assertFalse(Schema::hasColumn('mandis', 'state_id'));
        $this->assertTrue(Schema::hasColumn('mandis', 'district_id'));
    }

    public function test_admin_can_list_mandis_with_state_derived_from_district(): void
    {
        Mandi::create([
            'district_id' => $this->districtNagpur->id,
            'name' => 'Nagpur APMC Mandi',
            'slug' => 'nagpur-apmc-mandi',
            'code' => 'NGP-APMC',
            'market_type' => MarketType::APMC->value,
            'address' => 'Kalamna Market Yard, Nagpur',
            'pincode' => '440035',
            'sort_order' => 1,
            'status' => true,
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/admin/mandis')
            ->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'items' => [
                        '*' => [
                            'id',
                            'district_id',
                            'district' => ['id', 'name', 'slug'],
                            'state' => ['id', 'name', 'slug', 'code'],
                            'name',
                            'slug',
                            'code',
                            'market_type',
                            'market_type_label',
                            'address',
                            'pincode',
                            'sort_order',
                            'status',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
            ]);

        $item = $response->json('data.items.0');
        $this->assertEquals('Nagpur', $item['district']['name']);
        $this->assertEquals('Maharashtra', $item['state']['name']);
        $this->assertEquals('MH', $item['state']['code']);
        $this->assertEquals('apmc', $item['market_type']);
        $this->assertEquals('APMC Mandi', $item['market_type_label']);
    }

    public function test_admin_can_filter_mandis_by_state_via_relationship(): void
    {
        Mandi::create(['district_id' => $this->districtNagpur->id, 'name' => 'Nagpur APMC', 'slug' => 'nagpur-apmc', 'code' => 'NGP-APMC']);
        Mandi::create(['district_id' => $this->districtIndore->id, 'name' => 'Indore APMC', 'slug' => 'indore-apmc', 'code' => 'IND-APMC']);

        $res = $this->withToken($this->adminToken)
            ->getJson("/api/admin/mandis?state_id={$this->stateMP->id}")
            ->assertStatus(200);

        $this->assertCount(1, $res->json('data.items'));
        $this->assertEquals('Indore APMC', $res->json('data.items.0.name'));
    }

    public function test_admin_can_filter_mandis_by_district(): void
    {
        Mandi::create(['district_id' => $this->districtNagpur->id, 'name' => 'Nagpur APMC', 'slug' => 'nagpur-apmc', 'code' => 'NGP-APMC']);
        Mandi::create(['district_id' => $this->districtAkola->id, 'name' => 'Akola APMC', 'slug' => 'akola-apmc', 'code' => 'AKL-APMC']);

        $res = $this->withToken($this->adminToken)
            ->getJson("/api/admin/mandis?district_id={$this->districtAkola->id}")
            ->assertStatus(200);

        $this->assertCount(1, $res->json('data.items'));
        $this->assertEquals('Akola APMC', $res->json('data.items.0.name'));
    }

    public function test_admin_can_filter_mandis_by_market_type(): void
    {
        Mandi::create(['district_id' => $this->districtNagpur->id, 'name' => 'Mandi 1', 'slug' => 'm1', 'code' => 'M1', 'market_type' => MarketType::APMC->value]);
        Mandi::create(['district_id' => $this->districtNagpur->id, 'name' => 'Mandi 2', 'slug' => 'm2', 'code' => 'M2', 'market_type' => MarketType::SUB_YARD->value]);

        $res = $this->withToken($this->adminToken)
            ->getJson('/api/admin/mandis?market_type=sub_yard')
            ->assertStatus(200);

        $this->assertCount(1, $res->json('data.items'));
        $this->assertEquals('Mandi 2', $res->json('data.items.0.name'));
    }

    public function test_admin_can_filter_mandis_by_exact_pincode(): void
    {
        Mandi::create(['district_id' => $this->districtNagpur->id, 'name' => 'Mandi 1', 'slug' => 'm1', 'code' => 'M1', 'pincode' => '440035']);
        Mandi::create(['district_id' => $this->districtNagpur->id, 'name' => 'Mandi 2', 'slug' => 'm2', 'code' => 'M2', 'pincode' => '440001']);

        $res = $this->withToken($this->adminToken)
            ->getJson('/api/admin/mandis?pincode=440035')
            ->assertStatus(200);

        $this->assertCount(1, $res->json('data.items'));
        $this->assertEquals('Mandi 1', $res->json('data.items.0.name'));
    }

    public function test_admin_can_search_mandis_by_name_and_code(): void
    {
        Mandi::create(['district_id' => $this->districtNagpur->id, 'name' => 'Kalamna Market Yard', 'slug' => 'kalamna', 'code' => 'KLM-YRD']);
        Mandi::create(['district_id' => $this->districtNagpur->id, 'name' => 'Katol Sub-Yard', 'slug' => 'katol', 'code' => 'KTL-SUB']);

        $resName = $this->withToken($this->adminToken)
            ->getJson('/api/admin/mandis?search=Kalamna')
            ->assertStatus(200);
        $this->assertCount(1, $resName->json('data.items'));
        $this->assertEquals('Kalamna Market Yard', $resName->json('data.items.0.name'));

        $resCode = $this->withToken($this->adminToken)
            ->getJson('/api/admin/mandis?search=ktl-sub')
            ->assertStatus(200);
        $this->assertCount(1, $resCode->json('data.items'));
        $this->assertEquals('Katol Sub-Yard', $resCode->json('data.items.0.name'));
    }

    public function test_mandi_creation_validation_rules(): void
    {
        // Invalid market_type
        $this->withToken($this->adminToken)
            ->postJson('/api/admin/mandis', [
                'district_id' => $this->districtNagpur->id,
                'name' => 'Test Mandi',
                'code' => 'TM1',
                'market_type' => 'invalid_market_type',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['market_type']);

        // Invalid pincode (not 6 digits)
        $this->withToken($this->adminToken)
            ->postJson('/api/admin/mandis', [
                'district_id' => $this->districtNagpur->id,
                'name' => 'Test Mandi',
                'code' => 'TM2',
                'pincode' => '4400',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pincode']);

        // Invalid latitude / longitude out of bounds
        $this->withToken($this->adminToken)
            ->postJson('/api/admin/mandis', [
                'district_id' => $this->districtNagpur->id,
                'name' => 'Test Mandi',
                'code' => 'TM3',
                'latitude' => 105.5,
                'longitude' => -200.0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_mandi_code_normalization_trim_and_uppercase(): void
    {
        $res = $this->withToken($this->adminToken)
            ->postJson('/api/admin/mandis', [
                'district_id' => $this->districtNagpur->id,
                'name' => 'Kalamna Mandi',
                'code' => '   ngp-klm-01   ',
                'pincode' => '440035',
            ])
            ->assertStatus(201);

        $this->assertEquals('NGP-KLM-01', $res->json('data.code'));
        $this->assertDatabaseHas('mandis', [
            'district_id' => $this->districtNagpur->id,
            'code' => 'NGP-KLM-01',
        ]);
    }

    public function test_mandi_slug_uniqueness_within_district_including_trashed(): void
    {
        $m1 = Mandi::create([
            'district_id' => $this->districtNagpur->id,
            'name' => 'Grain Yard',
            'slug' => 'grain-yard',
            'code' => 'GY1',
        ]);
        $m1->delete(); // soft deleted

        $res = $this->withToken($this->adminToken)
            ->postJson('/api/admin/mandis', [
                'district_id' => $this->districtNagpur->id,
                'name' => 'Grain Yard',
                'code' => 'GY2',
            ])
            ->assertStatus(201);

        $this->assertEquals('grain-yard-2', $res->json('data.slug'));
    }

    public function test_same_mandi_slug_allowed_in_different_districts(): void
    {
        Mandi::create([
            'district_id' => $this->districtNagpur->id,
            'name' => 'Cotton Market',
            'slug' => 'cotton-market',
            'code' => 'CM-NGP',
        ]);

        $res = $this->withToken($this->adminToken)
            ->postJson('/api/admin/mandis', [
                'district_id' => $this->districtAkola->id,
                'name' => 'Cotton Market',
                'code' => 'CM-AKL',
            ])
            ->assertStatus(201);

        $this->assertEquals('cotton-market', $res->json('data.slug'));
    }

    public function test_admin_can_retrieve_single_mandi_with_state_derived_and_creator(): void
    {
        $mandi = Mandi::create([
            'district_id' => $this->districtNagpur->id,
            'name' => 'Kalamna Mandi',
            'slug' => 'kalamna-mandi',
            'code' => 'KLM-MND',
            'latitude' => 21.1458000,
            'longitude' => 79.0882000,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->withToken($this->adminToken)
            ->getJson("/api/admin/mandis/{$mandi->id}")
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'id' => $mandi->id,
                    'name' => 'Kalamna Mandi',
                    'district' => [
                        'id' => $this->districtNagpur->id,
                        'name' => 'Nagpur',
                    ],
                    'state' => [
                        'id' => $this->stateMH->id,
                        'name' => 'Maharashtra',
                    ],
                    'latitude' => 21.1458,
                    'longitude' => 79.0882,
                    'creator' => [
                        'id' => $this->admin->id,
                    ],
                ],
            ]);
    }

    public function test_admin_can_update_mandi_and_reassign_district(): void
    {
        $mandi = Mandi::create([
            'district_id' => $this->districtNagpur->id,
            'name' => 'Original Mandi',
            'slug' => 'original-mandi',
            'code' => 'ORIG-MND',
        ]);

        $this->withToken($this->adminToken)
            ->putJson("/api/admin/mandis/{$mandi->id}", [
                'district_id' => $this->districtAkola->id,
                'name' => 'Moved Mandi',
                'code' => ' moved-mnd ',
            ])
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'district_id' => $this->districtAkola->id,
                    'name' => 'Moved Mandi',
                    'code' => 'MOVED-MND',
                ],
            ]);

        $this->assertDatabaseHas('mandis', [
            'id' => $mandi->id,
            'district_id' => $this->districtAkola->id,
            'name' => 'Moved Mandi',
            'code' => 'MOVED-MND',
        ]);
    }

    public function test_mandi_district_reassignment_invalidates_old_and_new_district_and_state_caches(): void
    {
        $mandi = Mandi::create([
            'district_id' => $this->districtNagpur->id,
            'name' => 'Mandi X',
            'slug' => 'mandi-x',
            'code' => 'MX',
        ]);

        Cache::put(MandiService::CACHE_KEY_OPTIONS_DISTRICT_PREFIX.$this->districtNagpur->id, ['dummy'], 3600);
        Cache::put(MandiService::CACHE_KEY_OPTIONS_DISTRICT_PREFIX.$this->districtIndore->id, ['dummy'], 3600);
        Cache::put(MandiService::CACHE_KEY_OPTIONS_STATE_PREFIX.$this->stateMH->id, ['dummy'], 3600);
        Cache::put(MandiService::CACHE_KEY_OPTIONS_STATE_PREFIX.$this->stateMP->id, ['dummy'], 3600);

        $this->withToken($this->adminToken)
            ->putJson("/api/admin/mandis/{$mandi->id}", [
                'district_id' => $this->districtIndore->id,
                'name' => 'Mandi X Moved to MP',
            ])
            ->assertStatus(200);

        $this->assertFalse(Cache::has(MandiService::CACHE_KEY_OPTIONS_DISTRICT_PREFIX.$this->districtNagpur->id));
        $this->assertFalse(Cache::has(MandiService::CACHE_KEY_OPTIONS_DISTRICT_PREFIX.$this->districtIndore->id));
        $this->assertFalse(Cache::has(MandiService::CACHE_KEY_OPTIONS_STATE_PREFIX.$this->stateMH->id));
        $this->assertFalse(Cache::has(MandiService::CACHE_KEY_OPTIONS_STATE_PREFIX.$this->stateMP->id));
    }

    public function test_admin_can_update_single_mandi_status(): void
    {
        $mandi = Mandi::create(['district_id' => $this->districtNagpur->id, 'name' => 'Mandi S', 'slug' => 'mandi-s', 'code' => 'MS', 'status' => true]);

        $this->withToken($this->adminToken)
            ->patchJson("/api/admin/mandis/{$mandi->id}/status", ['status' => false])
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => ['status' => false],
            ]);

        $this->assertDatabaseHas('mandis', ['id' => $mandi->id, 'status' => false]);
    }

    public function test_admin_can_bulk_update_mandi_status(): void
    {
        $m1 = Mandi::create(['district_id' => $this->districtNagpur->id, 'name' => 'M1', 'slug' => 'm1', 'code' => 'M1', 'status' => true]);
        $m2 = Mandi::create(['district_id' => $this->districtNagpur->id, 'name' => 'M2', 'slug' => 'm2', 'code' => 'M2', 'status' => true]);

        $this->withToken($this->adminToken)
            ->patchJson('/api/admin/mandis/bulk-status', [
                'ids' => [$m1->id, $m2->id],
                'status' => false,
            ])
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => ['updated_count' => 2],
            ]);

        $this->assertDatabaseHas('mandis', ['id' => $m1->id, 'status' => false]);
        $this->assertDatabaseHas('mandis', ['id' => $m2->id, 'status' => false]);
    }

    public function test_admin_can_soft_delete_mandi(): void
    {
        $mandi = Mandi::create(['district_id' => $this->districtNagpur->id, 'name' => 'Mandi Del', 'slug' => 'mandi-del', 'code' => 'MDEL']);

        $this->withToken($this->adminToken)
            ->deleteJson("/api/admin/mandis/{$mandi->id}")
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Mandi deleted successfully.',
            ]);

        $this->assertSoftDeleted('mandis', ['id' => $mandi->id]);
    }

    public function test_admin_can_bulk_delete_mandis(): void
    {
        $m1 = Mandi::create(['district_id' => $this->districtNagpur->id, 'name' => 'M1', 'slug' => 'm1', 'code' => 'M1']);
        $m2 = Mandi::create(['district_id' => $this->districtNagpur->id, 'name' => 'M2', 'slug' => 'm2', 'code' => 'M2']);

        $this->withToken($this->adminToken)
            ->postJson('/api/admin/mandis/bulk-delete', [
                'ids' => [$m1->id, $m2->id],
            ])
            ->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => ['deleted_count' => 2],
            ]);

        $this->assertSoftDeleted('mandis', ['id' => $m1->id]);
        $this->assertSoftDeleted('mandis', ['id' => $m2->id]);
    }

    public function test_mandi_options_filtered_by_district(): void
    {
        Mandi::create(['district_id' => $this->districtNagpur->id, 'name' => 'Nagpur APMC', 'slug' => 'nagpur-apmc', 'code' => 'NGP-APMC', 'status' => true]);

        $res = $this->withToken($this->adminToken)
            ->getJson("/api/admin/mandis/options?district_id={$this->districtNagpur->id}")
            ->assertStatus(200);

        $this->assertCount(1, $res->json('data'));
        $this->assertTrue(Cache::has(MandiService::CACHE_KEY_OPTIONS_DISTRICT_PREFIX.$this->districtNagpur->id));
    }

    public function test_mandi_options_filtered_by_state(): void
    {
        Mandi::create(['district_id' => $this->districtNagpur->id, 'name' => 'Nagpur APMC', 'slug' => 'nagpur-apmc', 'code' => 'NGP-APMC', 'status' => true]);
        Mandi::create(['district_id' => $this->districtAkola->id, 'name' => 'Akola APMC', 'slug' => 'akola-apmc', 'code' => 'AKL-APMC', 'status' => true]);

        $res = $this->withToken($this->adminToken)
            ->getJson("/api/admin/mandis/options?state_id={$this->stateMH->id}")
            ->assertStatus(200);

        $this->assertCount(2, $res->json('data'));
        $this->assertTrue(Cache::has(MandiService::CACHE_KEY_OPTIONS_STATE_PREFIX.$this->stateMH->id));
    }

    public function test_mandi_options_rejects_conflicting_state_and_district_with_422(): void
    {
        // districtNagpur belongs to stateMH, not stateMP
        $this->withToken($this->adminToken)
            ->getJson("/api/admin/mandis/options?state_id={$this->stateMP->id}&district_id={$this->districtNagpur->id}")
            ->assertStatus(422)
            ->assertJson([
                'status' => false,
                'message' => 'The specified district does not belong to the selected state.',
            ]);
    }
}
