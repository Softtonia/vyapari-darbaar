<?php

namespace Tests\Feature\News;

use App\Enums\NewsStatus;
use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\NewsSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ScheduledNewsPublishingTest extends TestCase
{
    use RefreshDatabase;

    protected NewsSource $source;
    protected NewsCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->source = NewsSource::create(['name' => 'NCDEX', 'slug' => 'ncdex', 'status' => true]);
        $this->category = NewsCategory::create(['name' => 'Market', 'slug' => 'market', 'status' => true]);
    }

    public function test_due_scheduled_article_is_published(): void
    {
        $article = NewsArticle::create([
            'news_source_id' => $this->source->id,
            'news_category_id' => $this->category->id,
            'title' => 'Due Article',
            'slug' => 'due-article',
            'status' => NewsStatus::SCHEDULED->value,
            'scheduled_at' => now()->subMinute(),
        ]);

        Artisan::call('news:publish-scheduled');

        $article->refresh();
        $this->assertEquals(NewsStatus::PUBLISHED, $article->status);
        $this->assertNotNull($article->published_at);
        $this->assertNotNull($article->published_on);
    }

    public function test_future_scheduled_article_remains_scheduled(): void
    {
        $article = NewsArticle::create([
            'news_source_id' => $this->source->id,
            'news_category_id' => $this->category->id,
            'title' => 'Future Article',
            'slug' => 'future-article',
            'status' => NewsStatus::SCHEDULED->value,
            'scheduled_at' => now()->addHour(),
        ]);

        Artisan::call('news:publish-scheduled');

        $article->refresh();
        $this->assertEquals(NewsStatus::SCHEDULED, $article->status);
    }

    public function test_inactive_source_or_category_blocks_scheduled_publishing(): void
    {
        $inactiveSource = NewsSource::create(['name' => 'Inactive Source', 'slug' => 'inactive-src', 'status' => false]);

        $article = NewsArticle::create([
            'news_source_id' => $inactiveSource->id,
            'news_category_id' => $this->category->id,
            'title' => 'Blocked Article',
            'slug' => 'blocked-article',
            'status' => NewsStatus::SCHEDULED->value,
            'scheduled_at' => now()->subMinute(),
        ]);

        Artisan::call('news:publish-scheduled');

        $article->refresh();
        // Remains scheduled because source is inactive
        $this->assertEquals(NewsStatus::SCHEDULED, $article->status);
    }
}
