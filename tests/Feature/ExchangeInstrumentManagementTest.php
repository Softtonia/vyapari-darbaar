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

    public function test_admin_can_manually_create_future_instrument(): void
    {
        $payload = [
            'exchange_id' => $this->mcx->id,
            'exchange_commodity_mapping_id' => $this->mcxGoldMapping->id,
            'external_instrument_id' => 'MCX_GOLD_202610_FUT_MANUAL',
            'symbol' => 'GOLD26OCTFUT',
            'instrument_name' => 'MCX Gold Futures Oct 2026',
            'instrument_type' => 'future',
            'original_expiry_date' => '2026-10-05',
            'actual_expiry_date' => '2026-10-05',
            'lot_size' => 1.0,
            'tick_size' => 1.0,
            'quote_unit' => '10 GM',
            'contract_unit' => '1 KG',
            'lifecycle_status' => 'active',
            'is_enabled' => true,
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/admin/exchange-instruments', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Exchange instrument created successfully.')
            ->assertJsonPath('data.symbol', 'GOLD26OCTFUT')
            ->assertJsonPath('data.instrument_type', 'future')
            ->assertJsonPath('data.exchange.code', 'MCX');

        $this->assertDatabaseHas('exchange_instruments', [
            'external_instrument_id' => 'MCX_GOLD_202610_FUT_MANUAL',
            'symbol' => 'GOLD26OCTFUT',
            'is_enabled' => 1,
        ]);
    }

    public function test_admin_can_manually_create_option_instrument(): void
    {
        $payload = [
            'exchange_id' => $this->mcx->id,
            'exchange_commodity_mapping_id' => $this->mcxGoldMapping->id,
            'external_instrument_id' => 'MCX_GOLD_202610_75000_CE',
            'symbol' => 'GOLD26OCT75000CE',
            'instrument_name' => 'MCX Gold Options Oct 2026 Call 75000',
            'instrument_type' => 'option',
            'option_type' => 'call',
            'strike_price' => 75000.00,
            'actual_expiry_date' => '2026-10-05',
            'lot_size' => 1.0,
            'tick_size' => 0.5,
            'quote_unit' => '10 GM',
            'contract_unit' => '1 KG',
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/admin/exchange-instruments', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.instrument_type', 'option')
            ->assertJsonPath('data.option_type', 'call')
            ->assertJsonPath('data.strike_price', '75000.00000000');
    }

    public function test_admin_cannot_create_instrument_with_mismatched_exchange_mapping(): void
    {
        $payload = [
            'exchange_id' => $this->mcx->id,
            'exchange_commodity_mapping_id' => $this->ncdexGoldMapping->id, // Mismatch!
            'external_instrument_id' => 'MCX_MISMATCH_INST',
            'symbol' => 'MISMATCHFUT',
            'instrument_name' => 'Mismatch Test',
            'instrument_type' => 'future',
            'actual_expiry_date' => '2026-10-05',
            'lot_size' => 1.0,
            'tick_size' => 1.0,
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/admin/exchange-instruments', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['exchange_commodity_mapping_id']);
    }

    public function test_admin_can_manually_update_instrument(): void
    {
        $instrument = ExchangeInstrument::create([
            'exchange_id' => $this->mcx->id,
            'exchange_commodity_mapping_id' => $this->mcxGoldMapping->id,
            'external_instrument_id' => 'MCX_GOLD_EDIT_TEST',
            'symbol' => 'GOLD26OCTFUT',
            'instrument_name' => 'Initial Name',
            'instrument_type' => InstrumentType::FUTURE->value,
            'actual_expiry_date' => '2026-10-05',
            'lot_size' => '1.000000',
            'tick_size' => '1.00000000',
        ]);

        $updatePayload = [
            'instrument_name' => 'Updated Name via Admin',
            'lot_size' => 2.0,
            'tick_size' => 0.5,
            'lifecycle_status' => 'expired',
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->putJson("/api/admin/exchange-instruments/{$instrument->id}", $updatePayload);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.instrument_name', 'Updated Name via Admin')
            ->assertJsonPath('data.lot_size', '2.000000')
            ->assertJsonPath('data.lifecycle_status', 'expired');

        $this->assertDatabaseHas('exchange_instruments', [
            'id' => $instrument->id,
            'instrument_name' => 'Updated Name via Admin',
            'lifecycle_status' => 'expired',
        ]);
    }

    public function test_admin_can_delete_instrument_when_no_bhavcopies_exist(): void
    {
        $instrument = ExchangeInstrument::create([
            'exchange_id' => $this->mcx->id,
            'exchange_commodity_mapping_id' => $this->mcxGoldMapping->id,
            'external_instrument_id' => 'MCX_GOLD_DELETE_OK',
            'symbol' => 'GOLD26OCTFUT',
            'instrument_name' => 'Delete Me',
            'instrument_type' => InstrumentType::FUTURE->value,
            'actual_expiry_date' => '2026-10-05',
            'lot_size' => '1.000000',
            'tick_size' => '1.00000000',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/admin/exchange-instruments/{$instrument->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Exchange instrument deleted successfully.');

        $this->assertDatabaseMissing('exchange_instruments', [
            'id' => $instrument->id,
        ]);
    }

    public function test_admin_cannot_delete_instrument_when_bhavcopies_exist(): void
    {
        $instrument = ExchangeInstrument::create([
            'exchange_id' => $this->mcx->id,
            'exchange_commodity_mapping_id' => $this->mcxGoldMapping->id,
            'external_instrument_id' => 'MCX_GOLD_WITH_BHAVCOPY',
            'symbol' => 'GOLD26OCTFUT',
            'instrument_name' => 'Has History',
            'instrument_type' => InstrumentType::FUTURE->value,
            'actual_expiry_date' => '2026-10-05',
            'lot_size' => '1.000000',
            'tick_size' => '1.00000000',
        ]);

        \App\Models\MarketBhavcopy::create([
            'exchange_instrument_id' => $instrument->id,
            'trade_date' => '2026-09-15',
            'settlement_price' => 75200.0,
            'received_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson("/api/admin/exchange-instruments/{$instrument->id}");

        $response->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Cannot delete instrument because historical market data (bhavcopies) exists. You can set lifecycle_status to delisted or is_enabled to false instead.');

        $this->assertDatabaseHas('exchange_instruments', [
            'id' => $instrument->id,
        ]);
    }

    public function test_unauthorized_admin_without_permissions_is_rejected(): void
    {
        $limitedAdmin = Admin::create([
            'first_name' => 'Limited',
            'last_name' => 'Admin',
            'name' => 'Limited Admin',
            'email' => 'limited.admin@example.com',
            'password' => Hash::make('AdminPass@12345'),
            'status' => 'active',
        ]);
        // Do not assign role or permissions
        $token = $limitedAdmin->createToken('limited-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/exchange-instruments', [
                'exchange_id' => $this->mcx->id,
                'exchange_commodity_mapping_id' => $this->mcxGoldMapping->id,
                'external_instrument_id' => 'FORBIDDEN_TEST',
                'symbol' => 'FORBIDDEN',
                'instrument_name' => 'Forbidden',
                'instrument_type' => 'future',
                'actual_expiry_date' => '2026-10-05',
                'lot_size' => 1.0,
                'tick_size' => 1.0,
            ]);

        $response->assertStatus(403);
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
