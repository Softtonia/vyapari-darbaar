<?php

namespace Tests\Feature\News;

use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\NewsSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicNewsTest extends TestCase
{
    use RefreshDatabase;

    protected NewsSource $source;
    protected NewsCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->source = NewsSource::create(['name' => 'NCDEX', 'slug' => 'ncdex', 'status' => true]);
        $this->category = NewsCategory::create(['name' => 'Agri News', 'slug' => 'agri-news', 'status' => true]);
    }

    public function test_only_published_articles_are_publicly_visible(): void
    {
        // 1. Published (visible)
        NewsArticle::create([
            'news_source_id' => $this->source->id,
            'news_category_id' => $this->category->id,
            'title' => 'Visible Article',
            'slug' => 'visible-article',
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);

        // 2. Draft (hidden)
        NewsArticle::create([
            'news_source_id' => $this->source->id,
            'news_category_id' => $this->category->id,
            'title' => 'Draft Article',
            'slug' => 'draft-article',
            'status' => 'draft',
        ]);

        // 3. Scheduled in future (hidden)
        NewsArticle::create([
            'news_source_id' => $this->source->id,
            'news_category_id' => $this->category->id,
            'title' => 'Future Article',
            'slug' => 'future-article',
            'status' => 'scheduled',
            'scheduled_at' => now()->addHour(),
        ]);

        // 4. Archived (hidden)
        NewsArticle::create([
            'news_source_id' => $this->source->id,
            'news_category_id' => $this->category->id,
            'title' => 'Archived Article',
            'slug' => 'archived-article',
            'status' => 'archived',
            'published_at' => now()->subDays(2),
        ]);

        // 5. Soft deleted (hidden)
        $deleted = NewsArticle::create([
            'news_source_id' => $this->source->id,
            'news_category_id' => $this->category->id,
            'title' => 'Deleted Article',
            'slug' => 'deleted-article',
            'status' => 'published',
            'published_at' => now()->subHours(2),
        ]);
        $deleted->delete();

        $response = $this->getJson('/api/news');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.slug', 'visible-article');
    }

    public function test_article_detail_by_slug(): void
    {
        NewsArticle::create([
            'news_source_id' => $this->source->id,
            'news_category_id' => $this->category->id,
            'title' => 'Detailed Story',
            'slug' => 'detailed-story',
            'content' => '<p>Deep analysis.</p>',
            'status' => 'published',
            'published_at' => now()->subMinutes(10),
            'view_count' => 5,
        ]);

        $response = $this->getJson('/api/news/detailed-story');

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Detailed Story')
            ->assertJsonPath('data.content', '<p>Deep analysis.</p>')
            ->assertJsonPath('data.view_count', 6); // Incremented atomically
    }

    public function test_unpublished_slug_returns_404(): void
    {
        NewsArticle::create([
            'news_source_id' => $this->source->id,
            'news_category_id' => $this->category->id,
            'title' => 'Draft Secret',
            'slug' => 'draft-secret',
            'status' => 'draft',
        ]);

        $response = $this->getJson('/api/news/draft-secret');
        $response->assertStatus(404);
    }

    public function test_public_filters_search_and_pagination(): void
    {
        NewsArticle::create([
            'news_source_id' => $this->source->id,
            'news_category_id' => $this->category->id,
            'title' => 'Soybean Crop Estimates 2026',
            'short_description' => 'Acreage survey report.',
            'slug' => 'soybean-crop-estimates',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'is_featured' => true,
        ]);

        NewsArticle::create([
            'news_source_id' => $this->source->id,
            'news_category_id' => $this->category->id,
            'title' => 'Wheat Procurement Exceeds Target',
            'slug' => 'wheat-procurement',
            'status' => 'published',
            'published_at' => now()->subDays(2),
            'is_featured' => false,
        ]);

        // Search filter
        $searchResponse = $this->getJson('/api/news?search=Soybean');
        $searchResponse->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.slug', 'soybean-crop-estimates');

        // Featured filter
        $featResponse = $this->getJson('/api/news?featured=1');
        $featResponse->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.slug', 'soybean-crop-estimates');
    }

    public function test_public_categories_and_sources_endpoints(): void
    {
        $catResponse = $this->getJson('/api/news-categories');
        $catResponse->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $srcResponse = $this->getJson('/api/news-sources');
        $srcResponse->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }
}
