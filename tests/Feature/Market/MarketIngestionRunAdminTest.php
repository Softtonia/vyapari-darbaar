<?php

namespace Tests\Feature\Market;

use App\Enums\ExchangeType;
use App\Enums\IngestionSourceType;
use App\Enums\IngestionStatus;
use App\Models\Admin;
use App\Models\Exchange;
use App\Models\MarketIngestionRun;
use App\Models\Role;
use App\Services\MarketIngestionRunService;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MarketIngestionRunAdminTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $superAdmin;
    protected Admin $unprivilegedAdmin;
    protected Exchange $exchange;
    protected MarketIngestionRunService $service;

    protected string $superAdminToken;
    protected string $unprivilegedToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->superAdmin = Admin::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'email' => 'superadmin@example.com',
            'password' => bcrypt('Password@123'),
            'status' => 'active',
        ]);
        $this->superAdmin->assignRole('super_admin');
        $this->superAdminToken = $this->superAdmin->createToken('admin-token')->plainTextToken;

        $this->unprivilegedAdmin = Admin::create([
            'first_name' => 'NoPerm',
            'last_name' => 'Admin',
            'name' => 'NoPerm Admin',
            'username' => 'nopermadmin',
            'email' => 'noperm@example.com',
            'password' => bcrypt('Password@123'),
            'status' => 'active',
        ]);
        $this->unprivilegedToken = $this->unprivilegedAdmin->createToken('admin-token')->plainTextToken;

        $this->exchange = Exchange::create([
            'name' => 'National Commodity & Derivatives Exchange',
            'code' => 'NCDEX',
            'slug' => 'ncdex',
            'exchange_type' => ExchangeType::COMMODITY_DERIVATIVES,
            'timezone' => 'Asia/Kolkata',
            'status' => true,
        ]);

        $this->service = new MarketIngestionRunService();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/admin/market-ingestion-runs');
        $response->assertStatus(401);
    }

    public function test_admin_without_permission_is_forbidden(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->unprivilegedToken}",
        ])->getJson('/api/admin/market-ingestion-runs');

        $response->assertStatus(403);
    }

    public function test_super_admin_can_list_ingestion_runs_with_filters(): void
    {
        MarketIngestionRun::create([
            'exchange_id' => $this->exchange->id,
            'source_type' => IngestionSourceType::BHAVCOPY->value,
            'trade_date' => '2026-09-15',
            'status' => IngestionStatus::COMPLETED->value,
            'records_received' => 100,
            'records_inserted' => 100,
            'started_at' => Carbon::now(),
            'finished_at' => Carbon::now(),
        ]);

        MarketIngestionRun::create([
            'exchange_id' => $this->exchange->id,
            'source_type' => IngestionSourceType::REFERENCE_DATA->value,
            'trade_date' => null,
            'status' => IngestionStatus::PARTIAL->value,
            'records_received' => 50,
            'records_inserted' => 45,
            'records_skipped' => 5,
            'started_at' => Carbon::now(),
            'finished_at' => Carbon::now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson("/api/admin/market-ingestion-runs?exchange_id={$this->exchange->id}&source_type=bhavcopy");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Market ingestion runs fetched successfully.',
            ]);

        $items = $response->json('data.items');
        $this->assertCount(1, $items);
        $this->assertSame('bhavcopy', $items[0]['source_type']);
        $this->assertSame('2026-09-15', $items[0]['trade_date']);
    }

    public function test_super_admin_can_view_single_run_details_with_bounded_error_summary(): void
    {
        $run = MarketIngestionRun::create([
            'exchange_id' => $this->exchange->id,
            'source_type' => IngestionSourceType::BHAVCOPY->value,
            'trade_date' => '2026-09-15',
            'source_checksum' => hash('sha256', 'sample-feed-data'),
            'status' => IngestionStatus::PARTIAL->value,
            'records_received' => 100,
            'records_inserted' => 95,
            'records_skipped' => 5,
            'error_summary' => [
                'total_errors' => 5,
                'unmapped_count' => 5,
                'validation_error_count' => 0,
                'sample_errors' => [
                    ['row_index' => 12, 'error' => 'Unmapped instrument: EXT123'],
                ],
            ],
            'started_at' => Carbon::now(),
            'finished_at' => Carbon::now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->superAdminToken}",
        ])->getJson("/api/admin/market-ingestion-runs/{$run->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'id' => $run->id,
                    'status' => 'partial',
                    'records_received' => 100,
                    'records_inserted' => 95,
                    'records_skipped' => 5,
                    'error_summary' => [
                        'total_errors' => 5,
                    ],
                ],
            ]);
    }

    public function test_service_lifecycle_and_checksum_deduplication(): void
    {
        $checksum = hash('sha256', 'mock-source-content');

        // Create run
        $run = $this->service->createRun(
            $this->exchange->id,
            IngestionSourceType::BHAVCOPY,
            '2026-09-15',
            'ncdex_bhavcopy_20260915.csv',
            $checksum,
            'storage/app/private/market_feeds/ncdex_bhavcopy_20260915.csv'
        );

        $this->assertSame(IngestionStatus::PENDING, $run->status);

        // Mark processing
        $this->service->markProcessing($run);
        $this->assertSame(IngestionStatus::PROCESSING, $run->fresh()->status);
        $this->assertNotNull($run->fresh()->started_at);

        // Mark completed
        $this->service->markCompleted($run, [
            'records_received' => 200,
            'records_inserted' => 190,
            'records_updated' => 10,
            'records_skipped' => 0,
            'records_failed' => 0,
        ]);

        $fresh = $run->fresh();
        $this->assertSame(IngestionStatus::COMPLETED, $fresh->status);
        $this->assertSame(200, $fresh->records_received);
        $this->assertSame(190, $fresh->records_inserted);
        $this->assertSame(10, $fresh->records_updated);

        // Deduplication test: should find existing completed run
        $existing = $this->service->findCompletedRunByChecksum(
            $this->exchange->id,
            IngestionSourceType::BHAVCOPY,
            $checksum
        );

        $this->assertNotNull($existing);
        $this->assertSame($run->id, $existing->id);
    }
}
