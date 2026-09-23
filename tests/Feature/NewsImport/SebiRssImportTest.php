<?php

namespace Tests\Feature\NewsImport;

use App\Enums\NewsImportStatus;
use App\Enums\NewsStatus;
use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\NewsImportRun;
use App\Models\NewsSource;
use App\Services\SebiNewsImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SebiRssImportTest extends TestCase
{
    use RefreshDatabase;

    protected NewsSource $source;

    protected function setUp(): void
    {
        parent::setUp();

        config(['news_imports.sebi.enabled' => true]);
        config(['news_imports.sebi.source_code' => 'SEBI']);
        config(['news_imports.sebi.default_category_slug' => 'business']);

        $this->source = NewsSource::create([
            'name' => 'Securities and Exchange Board of India',
            'code' => 'SEBI',
            'slug' => 'sebi',
            'url'  => 'https://www.sebi.gov.in/',
            'type' => 'government',
            'is_active' => true,
        ]);
    }

    public function test_sebi_import_run()
    {
        NewsCategory::create([
            'name' => 'Business',
            'slug' => 'business',
            'is_active' => true,
        ]);

        Http::fake([
            config('news_imports.sebi.url') => Http::response($this->getMockRssXml(), 200),
        ]);

        $service = app(SebiNewsImportService::class);
        $run = $service->createPendingRun();

        $service->executeRun($run);

        $run->refresh();
        $this->assertEquals(NewsImportStatus::COMPLETED, $run->status);
        $this->assertEquals(2, $run->items_received);
        $this->assertEquals(2, $run->items_imported);
        $this->assertEquals(0, $run->items_skipped);
        $this->assertEquals(0, $run->items_failed);
        $this->assertNull($run->error_summary);

        $this->assertEquals(2, NewsArticle::count());

        $article1 = NewsArticle::where('external_id', '104657')->first();
        $this->assertNotNull($article1);
        $this->assertEquals('Appeal No. 7064 of 2026 filed by Amit Gangrade', $article1->title);
        $this->assertEquals(NewsStatus::DRAFT, $article1->status);
        $this->assertEquals('business', $article1->category->slug);
    }

    protected function getMockRssXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<rss version="2.0">
<channel>
<title>SEBI RSS Feed</title>
<link>https://www.sebi.gov.in/sebirss.xml</link>
<item>
<title>Appeal No. 7064 of 2026 filed by Amit Gangrade</title>
<description>Appeal No. 7064 of 2026 filed by Amit Gangrade</description>
<link>https://www.sebi.gov.in/enforcement/orders/sep-2026/appeal-no-7064_104657.html</link>
<pubDate>22 Sep, 2026 +0530</pubDate>
</item>
<item>
<title>Long Title Exceeding Length Limit To Verify Truncation Doesn't Throw Exception On Import So We Are Testing The String Limit Method In The Service Correctly For SEBI Items From The XML Feed</title>
<description>Short desc</description>
<link>https://www.sebi.gov.in/enforcement/orders/sep-2026/long-title_104658.html</link>
<pubDate>22 Sep, 2026 +0530</pubDate>
</item>
</channel>
</rss>
XML;
    }
}
