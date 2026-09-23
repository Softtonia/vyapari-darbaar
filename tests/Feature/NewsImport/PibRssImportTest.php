<?php

namespace Tests\Feature\NewsImport;

use App\Enums\NewsImportStatus;
use App\Models\Admin;
use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\NewsImportRun;
use App\Models\NewsSource;
use App\Services\PibNewsImportService;
use App\Jobs\ProcessPibRssImportJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for PIB RSS Auto-Import.
 * All tests use Http::fake() — the live PIB feed is NEVER called.
 */
class PibRssImportTest extends TestCase
{
    use RefreshDatabase;

    private const FEED_URL = 'https://pib.gov.in/RssMain.aspx?ModId=6&Lang=1&Regid=3';

    private const VALID_FEED = '<rss version="2.0"><channel><title>PIB India</title><link>https://pib.gov.in</link><item><title>Cabinet approves MSP for Kharif crops 2026</title><link>https://pib.gov.in/PressReleaseIframePage.aspx?PRID=2000001</link></item><item><title>PM inaugurates new agricultural infrastructure</title><link>https://pib.gov.in/PressReleaseIframePage.aspx?PRID=2000002</link></item></channel></rss>';

    private const SINGLE_ITEM_FEED = '<rss version="2.0"><channel><title>PIB India</title><link>https://pib.gov.in</link><item><title>Single Article Title</title><link>https://pib.gov.in/PressReleaseIframePage.aspx?PRID=3000001</link></item></channel></rss>';

    private const EMPTY_FEED = '<rss version="2.0"><channel><title>PIB India</title><link>https://pib.gov.in</link></channel></rss>';

    private const MALFORMED_XML = 'this is not valid xml at all!!!! <<< >>>';

    /**
     * Reset Spatie permission cache between tests.
     */
    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function createAdmin(): Admin
    {
        $admin = Admin::forceCreate([
            'first_name' => 'Test',
            'last_name'  => 'Admin',
            'full_name'  => 'Test Admin',
            'username'   => 'testadmin',
            'email'      => 'testadmin@example.com',
            'password'   => bcrypt('password'),
            'status'     => 'active',
            'is_default' => true,
        ]);

        // Create and assign super_admin role with all news-import permissions
        $role = Role::firstOrCreate(['name' => 'super_admin'], ['slug' => 'super_admin', 'guard_name' => 'web']);
        $viewPerm    = Permission::findOrCreate('news-import.view', 'web');
        $triggerPerm = Permission::findOrCreate('news-import.trigger', 'web');
        $role->givePermissionTo([$viewPerm, $triggerPerm]);
        $admin->assignRole($role);

        return $admin;
    }

    private function createPibSource(): NewsSource
    {
        return NewsSource::create([
            'name'        => 'Press Information Bureau (PIB)',
            'slug'        => 'pib',
            'code'        => 'PIB',
            'website_url' => 'https://pib.gov.in',
            'status'      => true,
            'sort_order'  => 1,
        ]);
    }

    private function createDefaultCategory(string $slug = 'government-news'): NewsCategory
    {
        return NewsCategory::create([
            'name'       => 'Government News',
            'slug'       => $slug,
            'status'     => true,
            'sort_order' => 1,
        ]);
    }

    private function triggerImport(Admin $admin): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/news-import/runs/trigger');
    }

    private function fakeFeed(string $xml = self::VALID_FEED): void
    {
        Http::fake(['*' => Http::response($xml, 200, ['Content-Type' => 'application/rss+xml'])]);
    }

    private function configPib(string $categorySlug = null): void
    {
        config([
            'news_imports.pib.source_code'           => 'PIB',
            'news_imports.pib.default_category_slug' => $categorySlug,
            'news_imports.pib.url'                   => self::FEED_URL,
            'news_imports.pib.lock_key'              => 'news:lock:import:pib',
            'news_imports.pib.lock_ttl'              => 600,
            'news_imports.pib.timeout_seconds'       => 30,
            'news_imports.pib.max_items'             => 50,
        ]);
    }

    // ===== TRIGGER API TESTS =====

    #[Test]
    public function trigger_creates_pending_run_before_dispatching_job(): void
    {
        Queue::fake();
        $admin = $this->createAdmin();
        $response = $this->triggerImport($admin);
        $response->assertStatus(202)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.status', 'pending');
        $runId = $response->json('data.run_id');
        $this->assertNotNull($runId);
        $this->assertDatabaseHas('news_import_runs', ['id' => $runId, 'status' => 'pending']);
        Queue::assertPushed(ProcessPibRssImportJob::class, fn ($job) => $job->runId === $runId);
    }

    #[Test]
    public function trigger_requires_authentication(): void
    {
        $this->postJson('/api/admin/news-import/runs/trigger')->assertStatus(401);
    }

    #[Test]
    public function trigger_returns_run_id_in_response(): void
    {
        Queue::fake();
        $admin = $this->createAdmin();
        $response = $this->triggerImport($admin);
        $response->assertStatus(202)
            ->assertJsonPath('message', 'PIB news import queued successfully.')
            ->assertJsonStructure(['status', 'message', 'data' => ['run_id', 'status']]);
    }

    #[Test]
    public function trigger_dispatches_job_to_news_import_queue(): void
    {
        Queue::fake();
        $admin = $this->createAdmin();
        $this->triggerImport($admin);
        Queue::assertPushedOn('news-import', ProcessPibRssImportJob::class);
    }

    // ===== LIST / SHOW API TESTS =====

    #[Test]
    public function index_returns_paginated_list_of_runs(): void
    {
        $admin = $this->createAdmin();
        NewsImportRun::create(['status' => 'completed', 'feed_url' => self::FEED_URL, 'items_received' => 2, 'items_imported' => 2, 'items_skipped' => 0, 'items_failed' => 0]);
        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/news-import/runs');
        $response->assertStatus(200)->assertJsonPath('status', true)
            ->assertJsonStructure(['data' => ['items', 'pagination' => ['current_page', 'per_page', 'total', 'last_page']]]);
        $this->assertCount(1, $response->json('data.items'));
    }

    #[Test]
    public function index_requires_authentication(): void
    {
        $this->getJson('/api/admin/news-import/runs')->assertStatus(401);
    }

    #[Test]
    public function index_filters_by_status(): void
    {
        $admin = $this->createAdmin();
        NewsImportRun::create(['status' => 'completed', 'feed_url' => self::FEED_URL]);
        NewsImportRun::create(['status' => 'failed', 'feed_url' => self::FEED_URL]);
        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/news-import/runs?status=completed');
        $response->assertStatus(200);
        $items = $response->json('data.items');
        $this->assertCount(1, $items);
        $this->assertEquals('completed', $items[0]['status']);
    }

    #[Test]
    public function show_returns_single_run_detail(): void
    {
        $admin = $this->createAdmin();
        $run = NewsImportRun::create(['status' => 'completed', 'feed_url' => self::FEED_URL, 'items_received' => 5, 'items_imported' => 4, 'items_skipped' => 1, 'items_failed' => 0]);
        $response = $this->actingAs($admin, 'sanctum')->getJson("/api/admin/news-import/runs/{$run->id}");
        $response->assertStatus(200)->assertJsonPath('data.id', $run->id)->assertJsonPath('data.status', 'completed');
    }

    // ===== IMPORT EXECUTION TESTS =====

    #[Test]
    public function successful_import_creates_draft_articles(): void
    {
        $this->createPibSource();
        $this->createDefaultCategory('government-news');
        $this->configPib('government-news');
        $this->fakeFeed();
        $service = app(PibNewsImportService::class);
        $run = $service->createPendingRun();
        $service->executeRun($run);
        $run->refresh();
        $this->assertEquals('completed', $run->status->value);
        $this->assertEquals(2, $run->items_imported);
        $this->assertEquals(0, $run->items_failed);
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->finished_at);
    }

    #[Test]
    public function imported_articles_are_draft_not_published(): void
    {
        $this->createPibSource();
        $this->createDefaultCategory('government-news');
        $this->configPib('government-news');
        $this->fakeFeed(self::SINGLE_ITEM_FEED);
        $service = app(PibNewsImportService::class);
        $run = $service->createPendingRun();
        $service->executeRun($run);
        $article = NewsArticle::first();
        $this->assertNotNull($article);
        $this->assertEquals('draft', $article->status->value);
    }

    #[Test]
    public function imported_articles_have_null_published_at(): void
    {
        $this->createPibSource();
        $this->createDefaultCategory('government-news');
        $this->configPib('government-news');
        $this->fakeFeed(self::SINGLE_ITEM_FEED);
        $service = app(PibNewsImportService::class);
        $run = $service->createPendingRun();
        $service->executeRun($run);
        $article = NewsArticle::first();
        $this->assertNull($article->published_at, 'published_at must be null — Admin must publish via CMS');
    }

    #[Test]
    public function imported_articles_are_not_featured_or_breaking(): void
    {
        $this->createPibSource();
        $this->createDefaultCategory('government-news');
        $this->configPib('government-news');
        $this->fakeFeed(self::SINGLE_ITEM_FEED);
        $service = app(PibNewsImportService::class);
        $run = $service->createPendingRun();
        $service->executeRun($run);
        $article = NewsArticle::first();
        $this->assertFalse((bool) $article->is_featured, 'is_featured must be false');
        $this->assertFalse((bool) $article->is_breaking, 'is_breaking must be false');
    }

    #[Test]
    public function imported_articles_have_external_id_and_imported_at(): void
    {
        $this->createPibSource();
        $this->createDefaultCategory('government-news');
        $this->configPib('government-news');
        $this->fakeFeed(self::SINGLE_ITEM_FEED);
        $service = app(PibNewsImportService::class);
        $run = $service->createPendingRun();
        $service->executeRun($run);
        $article = NewsArticle::first();
        $this->assertNotNull($article->external_id, 'external_id must be set');
        $this->assertNotNull($article->imported_at, 'imported_at must be set');
        $this->assertEquals('3000001', $article->external_id);
    }

    #[Test]
    public function imported_article_source_is_pib(): void
    {
        $this->createPibSource();
        $this->createDefaultCategory('government-news');
        $this->configPib('government-news');
        $this->fakeFeed(self::SINGLE_ITEM_FEED);
        $service = app(PibNewsImportService::class);
        $service->executeRun($service->createPendingRun());
        $article = NewsArticle::with('source')->first();
        $this->assertStringContainsString('pib.gov.in', $article->source_url);
        $this->assertEquals('PIB', $article->source->code);
    }

    // ===== DUPLICATE DETECTION TESTS =====

    #[Test]
    public function duplicate_guid_is_skipped_not_failed(): void
    {
        $this->createPibSource();
        $this->createDefaultCategory('government-news');
        $this->configPib('government-news');
        $service = app(PibNewsImportService::class);
        $this->fakeFeed(self::SINGLE_ITEM_FEED);
        $run1 = $service->createPendingRun();
        $service->executeRun($run1);
        $run1->refresh();
        $this->assertEquals(1, $run1->items_imported);
        $this->fakeFeed(self::SINGLE_ITEM_FEED);
        $run2 = $service->createPendingRun();
        $service->executeRun($run2);
        $run2->refresh();
        $this->assertEquals(0, $run2->items_imported);
        $this->assertEquals(1, $run2->items_skipped);
        $this->assertEquals('completed', $run2->status->value);
    }

    #[Test]
    public function same_feed_twice_is_idempotent(): void
    {
        $this->createPibSource();
        $this->createDefaultCategory('government-news');
        $this->configPib('government-news');
        $service = app(PibNewsImportService::class);
        for ($i = 0; $i < 3; $i++) {
            $this->fakeFeed(self::SINGLE_ITEM_FEED);
            $service->executeRun($service->createPendingRun());
        }
        $this->assertEquals(1, NewsArticle::count());
    }

    #[Test]
    public function source_url_secondary_duplicate_protection_works(): void
    {
        $source = $this->createPibSource();
        $category = $this->createDefaultCategory('government-news');
        $this->configPib('government-news');
        NewsArticle::create([
            'news_source_id'   => $source->id,
            'news_category_id' => $category->id,
            'content_type'     => 'press_release',
            'title'            => 'Existing article',
            'slug'             => 'existing-article',
            'source_url'       => 'https://pib.gov.in/PressReleaseIframePage.aspx?PRID=3000001',
            'status'           => 'draft',
            'is_featured'      => false,
            'is_breaking'      => false,
            'external_id'      => null,
        ]);
        $this->fakeFeed(self::SINGLE_ITEM_FEED);
        $service = app(PibNewsImportService::class);
        $run = $service->createPendingRun();
        $service->executeRun($run);
        $run->refresh();
        $this->assertEquals(0, $run->items_imported);
        $this->assertEquals(1, $run->items_skipped);
        $this->assertEquals(1, NewsArticle::count());
    }

    // ===== SOURCE/CATEGORY RESOLUTION TESTS =====

    #[Test]
    public function missing_source_fails_run_gracefully(): void
    {
        $this->configPib();
        $this->fakeFeed();
        $service = app(PibNewsImportService::class);
        $run = $service->createPendingRun();
        try {
            $service->executeRun($run);
        } catch (\Throwable $e) {}
        $run->refresh();
        $this->assertEquals('failed', $run->status->value);
        $this->assertNotNull($run->error_summary);
        $this->assertIsArray($run->error_summary);
    }

    #[Test]
    public function inactive_source_fails_run_gracefully(): void
    {
        NewsSource::create(['name' => 'PIB', 'slug' => 'pib', 'code' => 'PIB', 'website_url' => 'https://pib.gov.in', 'status' => false, 'sort_order' => 1]);
        $this->configPib();
        $this->fakeFeed();
        $service = app(PibNewsImportService::class);
        $run = $service->createPendingRun();
        try {
            $service->executeRun($run);
        } catch (\Throwable $e) {}
        $run->refresh();
        $this->assertEquals('failed', $run->status->value);
    }

    #[Test]
    public function source_is_not_auto_created_at_runtime(): void
    {
        $this->configPib();
        $this->fakeFeed();
        $service = app(PibNewsImportService::class);
        try {
            $service->executeRun($service->createPendingRun());
        } catch (\Throwable $e) {}
        $this->assertEquals(0, NewsSource::count());
    }

    #[Test]
    public function missing_category_slug_fails_gracefully(): void
    {
        $this->createPibSource();
        $this->configPib(null);
        $this->fakeFeed(self::SINGLE_ITEM_FEED);
        $service = app(PibNewsImportService::class);
        $run = $service->createPendingRun();
        try {
            $service->executeRun($run);
        } catch (\Throwable $e) {}
        $run->refresh();
        $this->assertEquals('failed', $run->status->value);
    }

    #[Test]
    public function missing_configured_category_fails_gracefully(): void
    {
        $this->createPibSource();
        $this->configPib('non-existent-slug');
        $this->fakeFeed();
        $service = app(PibNewsImportService::class);
        $run = $service->createPendingRun();
        try {
            $service->executeRun($run);
        } catch (\Throwable $e) {}
        $run->refresh();
        $this->assertEquals('failed', $run->status->value);
        $this->assertNotNull($run->error_summary);
    }

    #[Test]
    public function inactive_category_fails_run_gracefully(): void
    {
        $this->createPibSource();
        NewsCategory::create(['name' => 'Inactive', 'slug' => 'inactive-cat', 'status' => false, 'sort_order' => 1]);
        $this->configPib('inactive-cat');
        $this->fakeFeed();
        $service = app(PibNewsImportService::class);
        $run = $service->createPendingRun();
        try {
            $service->executeRun($run);
        } catch (\Throwable $e) {}
        $run->refresh();
        $this->assertEquals('failed', $run->status->value);
    }

    #[Test]
    public function category_is_not_auto_created_at_runtime(): void
    {
        $this->createPibSource();
        $this->configPib('brand-new-category');
        $this->fakeFeed();
        $service = app(PibNewsImportService::class);
        try {
            $service->executeRun($service->createPendingRun());
        } catch (\Throwable $e) {}
        $this->assertEquals(0, NewsCategory::count());
    }

    #[Test]
    public function category_resolved_by_slug_not_numeric_id(): void
    {
        $this->createPibSource();
        $category = $this->createDefaultCategory('agriculture');
        $this->configPib('agriculture');
        $this->fakeFeed(self::SINGLE_ITEM_FEED);
        $service = app(PibNewsImportService::class);
        $run = $service->createPendingRun();
        $service->executeRun($run);
        $run->refresh();
        $this->assertEquals('completed', $run->status->value);
        $this->assertEquals($category->id, NewsArticle::first()->news_category_id);
    }

    // ===== FEED / XML ERROR TESTS =====

    #[Test]
    public function failed_http_request_marks_run_as_failed(): void
    {
        $this->createPibSource();
        $this->configPib();
        Http::fake(['*' => Http::response('', 500)]);
        $service = app(PibNewsImportService::class);
        $run = $service->createPendingRun();
        try {
            $service->executeRun($run);
        } catch (\Throwable $e) {}
        $run->refresh();
        $this->assertEquals('failed', $run->status->value);
        $this->assertNotNull($run->error_summary);
    }

    #[Test]
    public function invalid_xml_response_marks_run_as_failed(): void
    {
        $this->createPibSource();
        $this->configPib();
        Http::fake(['*' => Http::response(self::MALFORMED_XML, 200)]);
        $service = app(PibNewsImportService::class);
        $run = $service->createPendingRun();
        try {
            $service->executeRun($run);
        } catch (\Throwable $e) {}
        $run->refresh();
        $this->assertEquals('failed', $run->status->value);
    }

    #[Test]
    public function empty_feed_marks_run_as_completed(): void
    {
        $this->createPibSource();
        $this->createDefaultCategory('government-news');
        $this->configPib('government-news');
        $this->fakeFeed(self::EMPTY_FEED);
        $service = app(PibNewsImportService::class);
        $run = $service->createPendingRun();
        $service->executeRun($run);
        $run->refresh();
        $this->assertEquals('completed', $run->status->value);
        $this->assertEquals(0, $run->items_imported);
    }

    // ===== STATUS LIFECYCLE TESTS =====

    #[Test]
    public function run_transitions_pending_to_completed(): void
    {
        $this->createPibSource();
        $this->createDefaultCategory('government-news');
        $this->configPib('government-news');
        $this->fakeFeed();
        $service = app(PibNewsImportService::class);
        $run = $service->createPendingRun();
        $this->assertEquals('pending', $run->status->value);
        $service->executeRun($run);
        $run->refresh();
        $this->assertEquals('completed', $run->status->value);
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->finished_at);
    }

    // ===== REDIS LOCK TESTS =====

    #[Test]
    public function redis_lock_prevents_concurrent_imports(): void
    {
        $this->createPibSource();
        $this->configPib();
        $this->fakeFeed(self::SINGLE_ITEM_FEED);
        $service = app(PibNewsImportService::class);
        $lock = Cache::lock('news:lock:import:pib', 600);
        $lock->get();
        try {
            $run = $service->createPendingRun();
            $service->executeRun($run);
            $run->refresh();
            $this->assertEquals('failed', $run->status->value);
            $this->assertNotNull($run->error_summary);
            $this->assertStringContainsString('already running', $run->error_summary[0]);
        } finally {
            $lock->release();
        }
    }

    // ===== JOB TESTS =====

    #[Test]
    public function job_is_configured_with_correct_queue_and_tries(): void
    {
        $job = new ProcessPibRssImportJob(1);
        $this->assertEquals('news-import', $job->queue);
        $this->assertEquals(3, $job->tries);
        $this->assertEquals(300, $job->timeout);
        $this->assertEquals([60, 300, 900], $job->backoff);
    }

    #[Test]
    public function job_handles_missing_run_gracefully(): void
    {
        $service = app(PibNewsImportService::class);
        $job = new ProcessPibRssImportJob(99999);
        $job->handle($service);
        $this->assertTrue(true); // no exception thrown
    }

    // ===== PUBLIC API TESTS =====

    #[Test]
    public function draft_imported_articles_not_visible_via_public_api(): void
    {
        $this->createPibSource();
        $this->createDefaultCategory('government-news');
        $this->configPib('government-news');
        $this->fakeFeed(self::SINGLE_ITEM_FEED);
        $service = app(PibNewsImportService::class);
        $service->executeRun($service->createPendingRun());
        $response = $this->getJson('/api/news');
        $response->assertStatus(200);
        $items = $response->json('data.items') ?? $response->json('data') ?? [];
        $this->assertCount(0, $items);
    }

    // ===== PERFORMANCE TESTS =====

    #[Test]
    public function duplicate_check_uses_bulk_query(): void
    {
        $this->createPibSource();
        $this->createDefaultCategory('government-news');
        $this->configPib('government-news');
        $this->fakeFeed(self::VALID_FEED);
        $queryCount = 0;
        DB::listen(function ($query) use (&$queryCount) {
            $sql = strtolower($query->sql);
            if (str_starts_with($sql, 'select') && (str_contains($sql, 'external_id') || str_contains($sql, 'source_url'))) {
                $queryCount++;
            }
        });
        $service = app(PibNewsImportService::class);
        $service->executeRun($service->createPendingRun());
        // Max 2 bulk queries (external_ids + source_urls) — not N+1 per item
        $this->assertLessThanOrEqual(2, $queryCount, 'Expected at most 2 bulk duplicate-check queries');
    }
}
