<?php

namespace Tests\Feature\Market;

use App\DTOs\Market\NormalizedBhavcopyRowDTO;
use App\Enums\ExchangeType;
use App\Enums\InstrumentLifecycleStatus;
use App\Enums\InstrumentType;
use App\Models\Commodity;
use App\Models\CommodityCategory;
use App\Models\Exchange;
use App\Models\ExchangeCommodityMapping;
use App\Models\ExchangeInstrument;
use App\Models\MarketBhavcopy;
use App\Services\MarketBhavcopyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketBhavcopyServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Exchange $ncdex;
    protected Exchange $mcx;
    protected ExchangeCommodityMapping $ncdexMapping;
    protected ExchangeInstrument $ncdexInstrument;
    protected ExchangeInstrument $mcxInstrument;
    protected MarketBhavcopyService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $category = CommodityCategory::create([
            'name' => 'Grains & Pulses',
            'slug' => 'grains-pulses',
            'status' => true,
        ]);

        $commodity = Commodity::create([
            'commodity_category_id' => $category->id,
            'name' => 'Chana',
            'slug' => 'chana',
            'code' => 'CHANA',
            'unit' => 'Quintal',
            'status' => true,
        ]);

        $this->ncdex = Exchange::create([
            'name' => 'National Commodity & Derivatives Exchange',
            'code' => 'NCDEX',
            'slug' => 'ncdex',
            'exchange_type' => ExchangeType::COMMODITY_DERIVATIVES,
            'timezone' => 'Asia/Kolkata',
            'status' => true,
        ]);

        $this->mcx = Exchange::create([
            'name' => 'Multi Commodity Exchange of India',
            'code' => 'MCX',
            'slug' => 'mcx',
            'exchange_type' => ExchangeType::COMMODITY_DERIVATIVES,
            'timezone' => 'Asia/Kolkata',
            'status' => true,
        ]);

        $this->ncdexMapping = ExchangeCommodityMapping::create([
            'exchange_id' => $this->ncdex->id,
            'commodity_id' => $commodity->id,
            'external_symbol' => 'CHANA',
            'status' => true,
        ]);

        $mcxMapping = ExchangeCommodityMapping::create([
            'exchange_id' => $this->mcx->id,
            'commodity_id' => $commodity->id,
            'external_symbol' => 'CHANA',
            'status' => true,
        ]);

        $this->ncdexInstrument = ExchangeInstrument::create([
            'exchange_id' => $this->ncdex->id,
            'exchange_commodity_mapping_id' => $this->ncdexMapping->id,
            'external_instrument_id' => 'NCDEX_CHANA_202610',
            'symbol' => 'CHANA-20OCT2026-FUT',
            'instrument_name' => 'Chana October 2026 Future',
            'instrument_type' => InstrumentType::FUTURE,
            'actual_expiry_date' => '2026-10-20',
            'lot_size' => 10,
            'tick_size' => 1,
            'lifecycle_status' => InstrumentLifecycleStatus::ACTIVE,
            'is_enabled' => true,
        ]);

        $this->mcxInstrument = ExchangeInstrument::create([
            'exchange_id' => $this->mcx->id,
            'exchange_commodity_mapping_id' => $mcxMapping->id,
            'external_instrument_id' => 'MCX_CHANA_202610',
            'symbol' => 'CHANA-20OCT2026-FUT',
            'instrument_name' => 'Chana October 2026 Future',
            'instrument_type' => InstrumentType::FUTURE,
            'actual_expiry_date' => '2026-10-20',
            'lot_size' => 10,
            'tick_size' => 1,
            'lifecycle_status' => InstrumentLifecycleStatus::ACTIVE,
            'is_enabled' => true,
        ]);

        $this->service = new MarketBhavcopyService();
    }

    public function test_bhavcopy_bulk_upsert_inserts_valid_rows(): void
    {
        $rows = [
            new NormalizedBhavcopyRowDTO(
                externalInstrumentId: 'NCDEX_CHANA_202610',
                tradeDate: '2026-09-15',
                openPrice: '5420.50000000',
                highPrice: '5480.00000000',
                lowPrice: '5410.00000000',
                closePrice: '5465.00000000',
                settlementPrice: '5460.00000000',
                volume: '150.000000',
                tradedValue: '8190000.000000',
                numberOfTrades: 42,
                openInterest: '1200.000000',
                changeInOpenInterest: '50.000000',
                sourceTimestamp: '2026-09-15 17:30:00',
            ),
        ];

        $stats = $this->service->upsertNormalizedRows($this->ncdex->id, $rows);

        $this->assertSame(1, $stats['records_received']);
        $this->assertSame(1, $stats['records_inserted']);
        $this->assertSame(0, $stats['records_updated']);
        $this->assertSame(0, $stats['records_skipped']);
        $this->assertSame(0, $stats['records_failed']);

        $this->assertDatabaseHas('market_bhavcopies', [
            'exchange_instrument_id' => $this->ncdexInstrument->id,
            'trade_date' => '2026-09-15',
            'open_price' => '5420.50000000',
            'number_of_trades' => 42,
        ]);
    }

    public function test_reimport_same_trade_date_updates_instead_of_duplicating(): void
    {
        $rows = [
            new NormalizedBhavcopyRowDTO(
                externalInstrumentId: 'NCDEX_CHANA_202610',
                tradeDate: '2026-09-15',
                openPrice: '5400.00000000',
                closePrice: '5450.00000000',
            ),
        ];

        $firstStats = $this->service->upsertNormalizedRows($this->ncdex->id, $rows);
        $this->assertSame(1, $firstStats['records_inserted']);

        // Updated close price in revised file
        $updatedRows = [
            new NormalizedBhavcopyRowDTO(
                externalInstrumentId: 'NCDEX_CHANA_202610',
                tradeDate: '2026-09-15',
                openPrice: '5400.00000000',
                closePrice: '5455.00000000',
            ),
        ];

        $secondStats = $this->service->upsertNormalizedRows($this->ncdex->id, $updatedRows);
        $this->assertSame(0, $secondStats['records_inserted']);
        $this->assertSame(1, $secondStats['records_updated']);

        $this->assertDatabaseCount('market_bhavcopies', 1);
        $this->assertDatabaseHas('market_bhavcopies', [
            'exchange_instrument_id' => $this->ncdexInstrument->id,
            'trade_date' => '2026-09-15',
            'close_price' => '5455.00000000',
        ]);
    }

    public function test_null_values_remain_null_and_explicit_zero_remains_zero(): void
    {
        $rows = [
            new NormalizedBhavcopyRowDTO(
                externalInstrumentId: 'NCDEX_CHANA_202610',
                tradeDate: '2026-09-15',
                openPrice: null, // Null in feed
                volume: '0.000000', // Explicit zero in feed
                tradedValue: null,
            ),
        ];

        $this->service->upsertNormalizedRows($this->ncdex->id, $rows);

        $record = MarketBhavcopy::where('exchange_instrument_id', $this->ncdexInstrument->id)
            ->where('trade_date', '2026-09-15')
            ->firstOrFail();

        $this->assertNull($record->open_price);
        $this->assertNull($record->traded_value);
        $this->assertSame('0.000000', $record->volume);
    }

    public function test_unmapped_external_instrument_is_skipped_and_tracked(): void
    {
        $rows = [
            new NormalizedBhavcopyRowDTO(
                externalInstrumentId: 'UNKNOWN_EXT_ID_999',
                tradeDate: '2026-09-15',
                openPrice: '1000.00000000',
            ),
        ];

        $stats = $this->service->upsertNormalizedRows($this->ncdex->id, $rows);

        $this->assertSame(1, $stats['records_received']);
        $this->assertSame(0, $stats['records_inserted']);
        $this->assertSame(1, $stats['records_skipped']);
        $this->assertSame(1, $stats['unmapped_count']);
        $this->assertNotEmpty($stats['sample_errors']);
        $this->assertDatabaseCount('market_bhavcopies', 0);
    }

    public function test_cross_exchange_isolation_in_resolution(): void
    {
        // Try importing MCX external ID using NCDEX exchange_id
        $rows = [
            new NormalizedBhavcopyRowDTO(
                externalInstrumentId: 'MCX_CHANA_202610',
                tradeDate: '2026-09-15',
                openPrice: '1000.00000000',
            ),
        ];

        $stats = $this->service->upsertNormalizedRows($this->ncdex->id, $rows);

        // Must be skipped because MCX token is not valid under NCDEX
        $this->assertSame(1, $stats['records_skipped']);
        $this->assertDatabaseCount('market_bhavcopies', 0);
    }

    public function test_cross_exchange_direct_instrument_id_isolation(): void
    {
        // Try importing MCX direct instrument ID under NCDEX exchange_id
        $rows = [
            new NormalizedBhavcopyRowDTO(
                externalInstrumentId: null,
                tradeDate: '2026-09-15',
                instrumentId: $this->mcxInstrument->id, // Belongs to MCX
                openPrice: '1000.00000000',
            ),
        ];

        $stats = $this->service->upsertNormalizedRows($this->ncdex->id, $rows);

        // Must be skipped because instrument ID does not belong to NCDEX
        $this->assertSame(1, $stats['records_skipped']);
        $this->assertSame(1, $stats['unmapped_count']);
        $this->assertDatabaseCount('market_bhavcopies', 0);
    }

    public function test_invalid_trade_date_is_marked_failed(): void
    {
        $rows = [
            new NormalizedBhavcopyRowDTO(
                externalInstrumentId: 'NCDEX_CHANA_202610',
                tradeDate: 'invalid-date',
                openPrice: '1000.00000000',
            ),
        ];

        $stats = $this->service->upsertNormalizedRows($this->ncdex->id, $rows);

        $this->assertSame(1, $stats['records_received']);
        $this->assertSame(0, $stats['records_inserted']);
        $this->assertSame(1, $stats['records_failed']);
        $this->assertSame(1, $stats['validation_error_count']);
    }

    public function test_market_bhavcopy_attaches_only_to_instrument_not_directly_to_commodity(): void
    {
        $rows = [
            new NormalizedBhavcopyRowDTO(
                externalInstrumentId: 'NCDEX_CHANA_202610',
                tradeDate: '2026-09-15',
                openPrice: '5420.00000000',
            ),
        ];

        $this->service->upsertNormalizedRows($this->ncdex->id, $rows);

        $bhavcopy = MarketBhavcopy::firstOrFail();
        $this->assertSame($this->ncdexInstrument->id, $bhavcopy->exchange_instrument_id);
        $this->assertFalse(\Schema::hasColumn('market_bhavcopies', 'commodity_id'));
        $this->assertFalse(\Schema::hasColumn('market_bhavcopies', 'exchange_id'));
    }
}
