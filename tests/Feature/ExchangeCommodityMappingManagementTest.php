<?php

namespace Tests\Feature;

use App\Enums\ExchangeType;
use App\Enums\InstrumentType;
use App\Models\Admin;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use App\Models\Exchange;
use App\Models\ExchangeCommodityMapping;
use App\Models\ExchangeInstrument;
use App\Services\ExchangeCommodityMappingService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExchangeCommodityMappingManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected string $adminToken;

    protected Exchange $mcx;

    protected Exchange $ncdex;

    protected Commodity $gold;

    protected Commodity $silver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->admin = Admin::create([
            'first_name' => 'Mapping',
            'last_name' => 'Admin',
            'name' => 'Mapping Admin',
            'email' => 'admin.mappings@example.com',
            'password' => Hash::make('AdminPass@12345'),
            'status' => 'active',
        ]);

        $this->admin->assignRole('admin');
        $this->adminToken = $this->admin->createToken('admin-token')->plainTextToken;

        $this->mcx = Exchange::create([
            'name' => 'Multi Commodity Exchange of India Limited',
            'slug' => 'mcx',
            'code' => 'MCX',
            'exchange_type' => ExchangeType::COMMODITY_DERIVATIVES->value,
            'status' => true,
        ]);

        $this->ncdex = Exchange::create([
            'name' => 'National Commodity & Derivatives Exchange Limited',
            'slug' => 'ncdex',
            'code' => 'NCDEX',
            'exchange_type' => ExchangeType::COMMODITY_DERIVATIVES->value,
            'status' => true,
        ]);

        $category = CommodityCategory::create([
            'name' => 'Metals',
            'slug' => 'metals',
            'status' => true,
        ]);

        $this->gold = Commodity::create([
            'commodity_category_id' => $category->id,
            'name' => 'Gold',
            'slug' => 'gold',
            'code' => 'GOLD',
            'unit' => 'GM',
            'status' => true,
        ]);

        $this->silver = Commodity::create([
            'commodity_category_id' => $category->id,
            'name' => 'Silver',
            'slug' => 'silver',
            'code' => 'SILVER',
            'unit' => 'KG',
            'status' => true,
        ]);
    }

    public function test_admin_can_list_mappings_with_eager_loading(): void
    {
        ExchangeCommodityMapping::create([
            'exchange_id' => $this->mcx->id,
            'commodity_id' => $this->gold->id,
            'external_symbol' => 'GOLD',
            'external_name' => 'Gold Standard',
            'status' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/admin/exchange-commodity-mappings');

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'items' => [
                        '*' => ['id', 'exchange_id', 'exchange', 'commodity_id', 'commodity', 'external_symbol', 'status'],
                    ],
                    'pagination',
                ],
            ]);
    }

    public function test_one_commodity_can_be_mapped_on_multiple_exchanges(): void
    {
        // Gold on MCX
        $responseMcx = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/admin/exchange-commodity-mappings', [
                'exchange_id' => $this->mcx->id,
                'commodity_id' => $this->gold->id,
                'external_symbol' => 'GOLD',
            ]);

        $responseMcx->assertStatus(201);

        // Gold on NCDEX
        $responseNcdex = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/admin/exchange-commodity-mappings', [
                'exchange_id' => $this->ncdex->id,
                'commodity_id' => $this->gold->id,
                'external_symbol' => 'GOLD',
            ]);

        $responseNcdex->assertStatus(201);

        $this->assertDatabaseHas('exchange_commodity_mappings', [
            'exchange_id' => $this->mcx->id,
            'commodity_id' => $this->gold->id,
            'external_symbol' => 'GOLD',
        ]);

        $this->assertDatabaseHas('exchange_commodity_mappings', [
            'exchange_id' => $this->ncdex->id,
            'commodity_id' => $this->gold->id,
            'external_symbol' => 'GOLD',
        ]);
    }

    public function test_same_commodity_can_have_multiple_product_mappings_on_same_exchange(): void
    {
        // Gold -> MCX GOLD
        $r1 = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/admin/exchange-commodity-mappings', [
                'exchange_id' => $this->mcx->id,
                'commodity_id' => $this->gold->id,
                'external_symbol' => 'GOLD',
                'external_name' => 'Gold Standard 1kg',
            ]);
        $r1->assertStatus(201);

        // Gold -> MCX GOLDM
        $r2 = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/admin/exchange-commodity-mappings', [
                'exchange_id' => $this->mcx->id,
                'commodity_id' => $this->gold->id,
                'external_symbol' => 'GOLDM',
                'external_name' => 'Gold Mini 100g',
            ]);
        $r2->assertStatus(201);

        // Gold -> MCX GOLDPETAL
        $r3 = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/admin/exchange-commodity-mappings', [
                'exchange_id' => $this->mcx->id,
                'commodity_id' => $this->gold->id,
                'external_symbol' => 'GOLDPETAL',
                'external_name' => 'Gold Petal 1g',
            ]);
        $r3->assertStatus(201);

        $this->assertEquals(3, ExchangeCommodityMapping::query()->where('exchange_id', $this->mcx->id)->where('commodity_id', $this->gold->id)->count());
    }

    public function test_cannot_create_duplicate_external_symbol_on_same_exchange(): void
    {
        ExchangeCommodityMapping::create([
            'exchange_id' => $this->mcx->id,
            'commodity_id' => $this->gold->id,
            'external_symbol' => 'GOLD',
            'status' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/admin/exchange-commodity-mappings', [
                'exchange_id' => $this->mcx->id,
                'commodity_id' => $this->silver->id,
                'external_symbol' => 'GOLD',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['external_symbol']);
    }

    public function test_cannot_create_mapping_for_inactive_parent_exchange_or_commodity(): void
    {
        $inactiveExchange = Exchange::create([
            'name' => 'Inactive Exchange',
            'slug' => 'inactive-ex',
            'code' => 'INACTIVEEX',
            'exchange_type' => ExchangeType::COMMODITY_DERIVATIVES->value,
            'status' => false,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/admin/exchange-commodity-mappings', [
                'exchange_id' => $inactiveExchange->id,
                'commodity_id' => $this->gold->id,
                'external_symbol' => 'INACT_GOLD',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['exchange_id']);
    }

    public function test_admin_cannot_delete_mapping_if_instruments_exist(): void
    {
        $mapping = ExchangeCommodityMapping::create([
            'exchange_id' => $this->mcx->id,
            'commodity_id' => $this->gold->id,
            'external_symbol' => 'GOLD',
            'status' => true,
        ]);

        ExchangeInstrument::create([
            'exchange_id' => $this->mcx->id,
            'exchange_commodity_mapping_id' => $mapping->id,
            'external_instrument_id' => 'MCX_GOLD_202610',
            'symbol' => 'GOLD26OCTFUT',
            'instrument_name' => 'MCX Gold Oct 2026',
            'instrument_type' => InstrumentType::FUTURE->value,
            'actual_expiry_date' => '2026-10-05',
            'status' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/admin/exchange-commodity-mappings/{$mapping->id}");

        $response->assertStatus(409)
            ->assertJsonPath('status', false)
            ->assertJsonPath('error', 'EXCHANGE_MAPPING_IN_USE');

        $this->assertDatabaseHas('exchange_commodity_mappings', ['id' => $mapping->id, 'deleted_at' => null]);
    }

    public function test_admin_can_delete_mapping_without_instruments(): void
    {
        $mapping = ExchangeCommodityMapping::create([
            'exchange_id' => $this->mcx->id,
            'commodity_id' => $this->gold->id,
            'external_symbol' => 'GOLD_TEMP',
            'status' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/admin/exchange-commodity-mappings/{$mapping->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->assertSoftDeleted('exchange_commodity_mappings', ['id' => $mapping->id]);
    }

    public function test_mapping_options_retrieval_and_cache_invalidation_on_mutation(): void
    {
        $cacheKey = ExchangeCommodityMappingService::CACHE_KEY_OPTIONS_EXCHANGE_PREFIX.$this->mcx->id;
        Cache::put($cacheKey, [['id' => 999, 'symbol' => 'OLD']], 3600);

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/admin/exchange-commodity-mappings', [
                'exchange_id' => $this->mcx->id,
                'commodity_id' => $this->gold->id,
                'external_symbol' => 'GOLD_FRESH',
            ]);

        $this->assertFalse(Cache::has($cacheKey));
    }
}
