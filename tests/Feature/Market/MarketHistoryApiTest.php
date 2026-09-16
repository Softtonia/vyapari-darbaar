<?php

namespace Tests\Feature\Market;

use App\Enums\ExchangeType;
use App\Enums\InstrumentLifecycleStatus;
use App\Enums\InstrumentType;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use App\Models\Exchange;
use App\Models\ExchangeCommodityMapping;
use App\Models\ExchangeInstrument;
use App\Models\MarketBhavcopy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketHistoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected Exchange $exchange;
    protected ExchangeCommodityMapping $mapping;
    protected ExchangeInstrument $instrument;
    protected ExchangeInstrument $expiredInstrument;

    protected function setUp(): void
    {
        parent::setUp();

        $category = CommodityCategory::create([
            'name' => 'Spices',
            'slug' => 'spices',
            'status' => true,
        ]);

        $commodity = Commodity::create([
            'commodity_category_id' => $category->id,
            'name' => 'Jeera',
            'slug' => 'jeera',
            'code' => 'JEERA',
            'unit' => 'Quintal',
            'status' => true,
        ]);

        $this->exchange = Exchange::create([
            'name' => 'National Commodity & Derivatives Exchange',
            'code' => 'NCDEX',
            'slug' => 'ncdex',
            'exchange_type' => ExchangeType::COMMODITY_DERIVATIVES,
            'timezone' => 'Asia/Kolkata',
            'status' => true,
        ]);

        $this->mapping = ExchangeCommodityMapping::create([
            'exchange_id' => $this->exchange->id,
            'commodity_id' => $commodity->id,
            'external_symbol' => 'JEERAUNJHA',
            'status' => true,
        ]);

        $this->instrument = ExchangeInstrument::create([
            'exchange_id' => $this->exchange->id,
            'exchange_commodity_mapping_id' => $this->mapping->id,
            'external_instrument_id' => 'NCDEX_JEERA_202610',
            'symbol' => 'JEERAUNJHA-20OCT2026-FUT',
            'instrument_name' => 'Jeera Unjha October 2026 Future',
            'instrument_type' => InstrumentType::FUTURE,
            'actual_expiry_date' => '2026-10-20',
            'lot_size' => 3,
            'tick_size' => 5,
            'lifecycle_status' => InstrumentLifecycleStatus::ACTIVE,
            'is_enabled' => true,
        ]);

        $this->expiredInstrument = ExchangeInstrument::create([
            'exchange_id' => $this->exchange->id,
            'exchange_commodity_mapping_id' => $this->mapping->id,
            'external_instrument_id' => 'NCDEX_JEERA_202608',
            'symbol' => 'JEERAUNJHA-20AUG2026-FUT',
            'instrument_name' => 'Jeera Unjha August 2026 Future',
            'instrument_type' => InstrumentType::FUTURE,
            'actual_expiry_date' => '2026-08-20',
            'lot_size' => 3,
            'tick_size' => 5,
            'lifecycle_status' => InstrumentLifecycleStatus::EXPIRED,
            'is_enabled' => true,
        ]);

        // Seed some history candles
        MarketBhavcopy::create([
            'exchange_instrument_id' => $this->instrument->id,
            'trade_date' => '2026-09-10',
            'open_price' => '26000.00000000',
            'high_price' => '26500.00000000',
            'low_price' => '25900.00000000',
            'close_price' => '26400.00000000',
            'settlement_price' => '26350.00000000',
            'volume' => '100.000000',
            'traded_value' => '79200000.000000',
            'number_of_trades' => 50,
            'open_interest' => '2000.000000',
            'change_in_open_interest' => '100.000000',
            'received_at' => Carbon::now(),
        ]);

        MarketBhavcopy::create([
            'exchange_instrument_id' => $this->instrument->id,
            'trade_date' => '2026-09-11',
            'open_price' => '26400.00000000',
            'high_price' => '26800.00000000',
            'low_price' => '26300.00000000',
            'close_price' => '26750.00000000',
            'settlement_price' => '26700.00000000',
            'volume' => '120.000000',
            'traded_value' => '96300000.000000',
            'number_of_trades' => 65,
            'open_interest' => '2100.000000',
            'change_in_open_interest' => '100.000000',
            'received_at' => Carbon::now(),
        ]);

        MarketBhavcopy::create([
            'exchange_instrument_id' => $this->expiredInstrument->id,
            'trade_date' => '2026-08-19',
            'open_price' => '25000.00000000',
            'close_price' => '25200.00000000',
            'volume' => '50.000000',
            'received_at' => Carbon::now(),
        ]);
    }

    public function test_can_fetch_instrument_history_with_defaults(): void
    {
        $response = $this->getJson("/api/markets/instruments/{$this->instrument->id}/history?from=2026-09-01&to=2026-09-20");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Historical market data retrieved successfully.',
                'data' => [
                    'interval' => '1D',
                    'from' => '2026-09-01',
                    'to' => '2026-09-20',
                    'instrument' => [
                        'id' => $this->instrument->id,
                        'symbol' => 'JEERAUNJHA-20OCT2026-FUT',
                        'exchange' => [
                            'code' => 'NCDEX',
                        ],
                        'commodity' => [
                            'code' => 'JEERA',
                        ],
                    ],
                ],
            ]);

        $items = $response->json('data.items');
        $this->assertCount(2, $items);
        $this->assertSame('2026-09-10', $items[0]['trade_date']);
        $this->assertSame('2026-09-11', $items[1]['trade_date']);
    }

    public function test_unsupported_interval_returns_422(): void
    {
        $response = $this->getJson("/api/markets/instruments/{$this->instrument->id}/history?interval=5m");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['interval']);
    }

    public function test_from_greater_than_to_returns_422(): void
    {
        $response = $this->getJson("/api/markets/instruments/{$this->instrument->id}/history?from=2026-09-20&to=2026-09-10");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['from']);
    }

    public function test_history_is_available_for_expired_contracts(): void
    {
        $response = $this->getJson("/api/markets/instruments/{$this->expiredInstrument->id}/history?from=2026-08-01&to=2026-08-30");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'instrument' => [
                        'id' => $this->expiredInstrument->id,
                        'lifecycle_status' => 'expired',
                    ],
                ],
            ]);

        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_disabled_instrument_history_is_hidden_from_public_api(): void
    {
        $this->instrument->update(['is_enabled' => false]);

        $response = $this->getJson("/api/markets/instruments/{$this->instrument->id}/history?from=2026-09-01&to=2026-09-20");

        $response->assertStatus(404)
            ->assertJson([
                'status' => false,
                'message' => 'Exchange instrument is unavailable or inactive.',
            ]);
    }
}
