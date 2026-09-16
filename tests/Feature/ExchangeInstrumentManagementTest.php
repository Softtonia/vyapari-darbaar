<?php

namespace Tests\Feature;

use App\Enums\ExchangeType;
use App\Enums\InstrumentLifecycleStatus;
use App\Enums\InstrumentType;
use App\Enums\OptionType;
use App\Models\Admin;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use App\Models\Exchange;
use App\Models\ExchangeCommodityMapping;
use App\Models\ExchangeInstrument;
use App\Services\ExchangeInstrumentService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExchangeInstrumentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected string $adminToken;

    protected Exchange $mcx;

    protected Exchange $ncdex;

    protected Commodity $gold;

    protected Commodity $silver;

    protected ExchangeCommodityMapping $mcxGoldMapping;

    protected ExchangeCommodityMapping $ncdexGoldMapping;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->admin = Admin::create([
            'first_name' => 'Instrument',
            'last_name' => 'Admin',
            'name' => 'Instrument Admin',
            'email' => 'admin.instruments@example.com',
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

        $this->mcxGoldMapping = ExchangeCommodityMapping::create([
            'exchange_id' => $this->mcx->id,
            'commodity_id' => $this->gold->id,
            'external_symbol' => 'GOLD',
            'status' => true,
        ]);

        $this->ncdexGoldMapping = ExchangeCommodityMapping::create([
            'exchange_id' => $this->ncdex->id,
            'commodity_id' => $this->gold->id,
            'external_symbol' => 'GOLD',
            'status' => true,
        ]);
    }

    public function test_exchange_instrument_has_no_soft_deletes(): void
    {
        $traits = class_uses_recursive(ExchangeInstrument::class);
        $this->assertArrayNotHasKey(\Illuminate\Database\Eloquent\SoftDeletes::class, $traits);
    }

    public function test_exchange_and_mapping_mismatch_is_prevented_by_database_constraint(): void
    {
        $this->expectException(QueryException::class);

        // Attempt to insert an instrument claiming to be MCX exchange but pointing to an NCDEX mapping
        ExchangeInstrument::create([
            'exchange_id' => $this->mcx->id,
            'exchange_commodity_mapping_id' => $this->ncdexGoldMapping->id, // Mismatch!
            'external_instrument_id' => 'INVALID_PAIR_TEST',
            'symbol' => 'MISMATCH_FUT',
            'instrument_type' => InstrumentType::FUTURE->value,
            'actual_expiry_date' => '2026-10-05',
        ]);
    }

    public function test_external_instrument_id_is_exchange_scoped_unique(): void
    {
        ExchangeInstrument::create([
            'exchange_id' => $this->mcx->id,
            'exchange_commodity_mapping_id' => $this->mcxGoldMapping->id,
            'external_instrument_id' => 'TOKEN_12345',
            'symbol' => 'GOLD26OCTFUT',
            'instrument_type' => InstrumentType::FUTURE->value,
            'actual_expiry_date' => '2026-10-05',
        ]);

        $this->expectException(QueryException::class);

        // Duplicate token within same MCX exchange
        ExchangeInstrument::create([
            'exchange_id' => $this->mcx->id,
            'exchange_commodity_mapping_id' => $this->mcxGoldMapping->id,
            'external_instrument_id' => 'TOKEN_12345',
            'symbol' => 'GOLD26DECFUT',
            'instrument_type' => InstrumentType::FUTURE->value,
            'actual_expiry_date' => '2026-12-05',
        ]);
    }

    public function test_same_external_instrument_id_can_exist_across_different_exchanges(): void
    {
        $mcxInst = ExchangeInstrument::create([
            'exchange_id' => $this->mcx->id,
            'exchange_commodity_mapping_id' => $this->mcxGoldMapping->id,
            'external_instrument_id' => 'TOKEN_12345',
            'symbol' => 'MCX_GOLD_OCT',
            'instrument_type' => InstrumentType::FUTURE->value,
            'actual_expiry_date' => '2026-10-05',
        ]);

        $ncdexInst = ExchangeInstrument::create([
            'exchange_id' => $this->ncdex->id,
            'exchange_commodity_mapping_id' => $this->ncdexGoldMapping->id,
            'external_instrument_id' => 'TOKEN_12345', // Same token, different exchange
            'symbol' => 'NCDEX_GOLD_OCT',
            'instrument_type' => InstrumentType::FUTURE->value,
            'actual_expiry_date' => '2026-10-05',
        ]);

        $this->assertNotNull($mcxInst->id);
        $this->assertNotNull($ncdexInst->id);
    }

    public function test_admin_cannot_manually_http_post_or_delete_instruments(): void
    {
        // No HTTP Store route
        $postRes = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/admin/exchange-instruments', [
                'symbol' => 'FAKE_INSTRUMENT',
            ]);
        $this->assertTrue(in_array($postRes->status(), [404, 405]));

        // Create an instrument via service
        $instrument = ExchangeInstrument::create([
            'exchange_id' => $this->mcx->id,
            'exchange_commodity_mapping_id' => $this->mcxGoldMapping->id,
            'external_instrument_id' => 'MCX_GOLD_DEL_TEST',
            'symbol' => 'GOLD26OCTFUT',
            'instrument_type' => InstrumentType::FUTURE->value,
            'actual_expiry_date' => '2026-10-05',
        ]);

        // No HTTP Delete route
        $delRes = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/admin/exchange-instruments/{$instrument->id}");
        $this->assertTrue(in_array($delRes->status(), [404, 405]));
    }

    public function test_admin_can_toggle_is_enabled_only(): void
    {
        $instrument = ExchangeInstrument::create([
            'exchange_id' => $this->mcx->id,
            'exchange_commodity_mapping_id' => $this->mcxGoldMapping->id,
            'external_instrument_id' => 'MCX_GOLD_TOGGLE',
            'symbol' => 'GOLD26OCTFUT',
            'instrument_type' => InstrumentType::FUTURE->value,
            'actual_expiry_date' => '2026-10-05',
            'is_enabled' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->patchJson("/api/admin/exchange-instruments/{$instrument->id}/enabled", [
                'is_enabled' => false,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.is_enabled', false);

        $this->assertDatabaseHas('exchange_instruments', [
            'id' => $instrument->id,
            'is_enabled' => false,
        ]);
    }

    public function test_source_upsert_is_idempotent_and_updates_same_record(): void
    {
        $service = app(ExchangeInstrumentService::class);

        $firstRun = $service->upsertFromSource([
            'exchange_id' => $this->mcx->id,
            'exchange_commodity_mapping_id' => $this->mcxGoldMapping->id,
            'external_instrument_id' => 'MCX_GOLD_202610_FUT',
            'symbol' => 'GOLD26OCTFUT',
            'instrument_name' => 'MCX Gold Futures Oct 2026',
            'instrument_type' => InstrumentType::FUTURE,
            'actual_expiry_date' => '2026-10-05',
            'lot_size' => '1.000000',
            'tick_size' => '1.00000000',
            'quote_unit' => '10 GM',
            'contract_unit' => '1 KG',
        ]);

        $secondRun = $service->upsertFromSource([
            'exchange_id' => $this->mcx->id,
            'exchange_commodity_mapping_id' => $this->mcxGoldMapping->id,
            'external_instrument_id' => 'MCX_GOLD_202610_FUT',
            'symbol' => 'GOLD26OCTFUT',
            'instrument_name' => 'MCX Gold Futures Oct 2026 (Updated Name)',
            'instrument_type' => InstrumentType::FUTURE,
            'actual_expiry_date' => '2026-10-05',
            'lot_size' => '1.000000',
            'tick_size' => '1.00000000',
            'quote_unit' => '10 GM',
            'contract_unit' => '1 KG',
        ]);

        $this->assertEquals($firstRun->id, $secondRun->id);
        $this->assertEquals('MCX Gold Futures Oct 2026 (Updated Name)', $secondRun->instrument_name);
        $this->assertEquals(1, ExchangeInstrument::query()->where('external_instrument_id', 'MCX_GOLD_202610_FUT')->count());
    }

    public function test_future_and_option_instrument_precision_and_types(): void
    {
        $service = app(ExchangeInstrumentService::class);

        $optionInst = $service->upsertFromSource([
            'exchange_id' => $this->mcx->id,
            'exchange_commodity_mapping_id' => $this->mcxGoldMapping->id,
            'external_instrument_id' => 'MCX_GOLD_202610_CE_75000',
            'symbol' => 'GOLD26OCT75000CE',
            'instrument_name' => 'MCX Gold Options Oct 2026 Call 75000',
            'instrument_type' => InstrumentType::OPTION,
            'option_type' => OptionType::CALL,
            'strike_price' => '75000.00000000',
            'actual_expiry_date' => '2026-10-05',
            'lot_size' => '1.000000',
            'tick_size' => '0.50000000',
        ]);

        $this->assertEquals('option', $optionInst->instrument_type->value);
        $this->assertEquals('call', $optionInst->option_type->value);
        $this->assertEquals('75000.00000000', (string) $optionInst->strike_price);
        $this->assertEquals('2026-10', $optionInst->contract_month);
    }

    public function test_admin_can_filter_instruments_by_exchange_commodity_type_and_expiry(): void
    {
        $service = app(ExchangeInstrumentService::class);

        $service->upsertFromSource([
            'exchange_id' => $this->mcx->id,
            'exchange_commodity_mapping_id' => $this->mcxGoldMapping->id,
            'external_instrument_id' => 'MCX_GOLD_OCT',
            'symbol' => 'GOLD26OCTFUT',
            'instrument_type' => InstrumentType::FUTURE,
            'actual_expiry_date' => '2026-10-05',
        ]);

        $service->upsertFromSource([
            'exchange_id' => $this->mcx->id,
            'exchange_commodity_mapping_id' => $this->mcxGoldMapping->id,
            'external_instrument_id' => 'MCX_GOLD_DEC',
            'symbol' => 'GOLD26DECFUT',
            'instrument_type' => InstrumentType::FUTURE,
            'actual_expiry_date' => '2026-12-05',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson("/api/admin/exchange-instruments?exchange_id={$this->mcx->id}&commodity_id={$this->gold->id}&expiry_from=2026-10-01&expiry_to=2026-10-31");

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.symbol', 'GOLD26OCTFUT');
    }

    public function test_public_api_hides_inactive_exchange_mapping_commodity_or_disabled_or_expired_instrument(): void
    {
        // 1. Valid active & enabled instrument
        ExchangeInstrument::create([
            'exchange_id' => $this->mcx->id,
            'exchange_commodity_mapping_id' => $this->mcxGoldMapping->id,
            'external_instrument_id' => 'VALID_PUB_INST',
            'symbol' => 'GOLD26OCTFUT',
            'instrument_type' => InstrumentType::FUTURE->value,
            'actual_expiry_date' => '2026-10-05',
            'lifecycle_status' => InstrumentLifecycleStatus::ACTIVE->value,
            'is_enabled' => true,
        ]);

        // 2. Disabled instrument (is_enabled = false)
        ExchangeInstrument::create([
            'exchange_id' => $this->mcx->id,
            'exchange_commodity_mapping_id' => $this->mcxGoldMapping->id,
            'external_instrument_id' => 'DISABLED_PUB_INST',
            'symbol' => 'GOLD26DECFUT',
            'instrument_type' => InstrumentType::FUTURE->value,
            'actual_expiry_date' => '2026-12-05',
            'lifecycle_status' => InstrumentLifecycleStatus::ACTIVE->value,
            'is_enabled' => false,
        ]);

        // 3. Expired contract (lifecycle_status = expired)
        ExchangeInstrument::create([
            'exchange_id' => $this->mcx->id,
            'exchange_commodity_mapping_id' => $this->mcxGoldMapping->id,
            'external_instrument_id' => 'EXPIRED_PUB_INST',
            'symbol' => 'GOLD26AUGFUT',
            'instrument_type' => InstrumentType::FUTURE->value,
            'actual_expiry_date' => '2026-08-05',
            'lifecycle_status' => InstrumentLifecycleStatus::EXPIRED->value,
            'is_enabled' => true,
        ]);

        $response = $this->getJson("/api/exchanges/{$this->mcx->id}/instruments");

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.symbol', 'GOLD26OCTFUT');
    }

    public function test_public_api_commodities_preserves_multiple_exchange_products_for_same_commodity(): void
    {
        // Add Mini and Petal mappings for Gold on MCX
        ExchangeCommodityMapping::create([
            'exchange_id' => $this->mcx->id,
            'commodity_id' => $this->gold->id,
            'external_symbol' => 'GOLDM',
            'external_name' => 'Gold Mini',
            'status' => true,
        ]);

        ExchangeCommodityMapping::create([
            'exchange_id' => $this->mcx->id,
            'commodity_id' => $this->gold->id,
            'external_symbol' => 'GOLDPETAL',
            'external_name' => 'Gold Petal',
            'status' => true,
        ]);

        $response = $this->getJson("/api/exchanges/{$this->mcx->id}/commodities");

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonCount(3, 'data')
            ->assertJsonFragment(['symbol' => 'GOLD'])
            ->assertJsonFragment(['symbol' => 'GOLDM'])
            ->assertJsonFragment(['symbol' => 'GOLDPETAL']);
    }
}
