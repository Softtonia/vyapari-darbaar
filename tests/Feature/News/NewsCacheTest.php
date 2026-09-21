<?php

namespace Tests\Feature\News;

use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\NewsSource;
use App\Services\NewsArticleService;
use App\Services\NewsCategoryService;
use App\Services\NewsSourceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_news_options_caching_and_invalidation(): void
    {
        $sourceService = app(NewsSourceService::class);

        $source = NewsSource::create(['name' => 'Source 1', 'slug' => 'source-1', 'status' => true]);

        $options1 = $sourceService->getOptions();
        $this->assertCount(1, $options1);

        // Update source via service
        $sourceService->updateSource($source, ['name' => 'Source 1 Updated']);

        $options2 = $sourceService->getOptions();
        $this->assertEquals('Source 1 Updated', $options2[0]['name']);
    }

    public function test_public_article_cache_invalidation_on_update(): void
    {
        $source = NewsSource::create(['name' => 'Source 1', 'slug' => 'src-1', 'status' => true]);
        $category = NewsCategory::create(['name' => 'Category 1', 'slug' => 'cat-1', 'status' => true]);

        $article = NewsArticle::create([
            'news_source_id' => $source->id,
            'news_category_id' => $category->id,
            'title' => 'Original Title',
            'slug' => 'original-title',
            'status' => 'published',
            'published_at' => now()->subHour(),
            'content' => '<p>Original content</p>',
        ]);

        $articleService = app(NewsArticleService::class);

        // Fetch through service which caches
        $cached = $articleService->getPublicArticleBySlug('original-title');
        $this->assertNotNull($cached);
        $this->assertEquals('Original Title', $cached->title);

        // Update article via service (slug updated)
        $articleService->updateArticle($article, [
            'title' => 'Updated Title',
            'slug' => 'updated-title',
            'content' => '<p>New</p>',
        ]);

        // Old slug should be invalidated and return null (since slug changed)
        $oldSlugCached = $articleService->getPublicArticleBySlug('original-title');
        $this->assertNull($oldSlugCached);

        // New slug should be available with updated title
        $refetched = $articleService->getPublicArticleBySlug('updated-title');
        $this->assertNotNull($refetched);
        $this->assertEquals('Updated Title', $refetched->title);
    }
}
