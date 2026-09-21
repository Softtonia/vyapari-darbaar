<?php

namespace Tests\Feature\News;

use App\Enums\NewsContentType;
use App\Enums\NewsStatus;
use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\NewsSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsArticleManagementTest extends TestCase
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

    public function test_admin_can_create_draft_article(): void
    {
        $token = $this->createAdminToken(['news.create']);

        $response = $this->withToken($token)->postJson('/api/admin/news', [
            'news_source_id' => $this->source->id,
            'news_category_id' => $this->category->id,
            'content_type' => 'news',
            'title' => 'Chana Futures Rally 2 Percent',
            'content' => '<p>Chana futures surged today.</p>',
            'status' => 'draft',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Chana Futures Rally 2 Percent')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.slug', 'chana-futures-rally-2-percent');

        $this->assertDatabaseHas('news_articles', [
            'title' => 'Chana Futures Rally 2 Percent',
            'status' => 'draft',
        ]);
    }

    public function test_scheduled_article_requires_future_date(): void
    {
        $token = $this->createAdminToken(['news.create', 'news.publish']);

        // Past scheduled date must fail
        $response = $this->withToken($token)->postJson('/api/admin/news', [
            'news_source_id' => $this->source->id,
            'news_category_id' => $this->category->id,
            'content_type' => 'news',
            'title' => 'Scheduled News',
            'content' => '<p>Content</p>',
            'status' => 'scheduled',
            'scheduled_at' => now()->subDay()->toDateTimeString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['scheduled_at']);

        // Future scheduled date succeeds
        $futureResponse = $this->withToken($token)->postJson('/api/admin/news', [
            'news_source_id' => $this->source->id,
            'news_category_id' => $this->category->id,
            'content_type' => 'news',
            'title' => 'Scheduled Future News',
            'content' => '<p>Content</p>',
            'status' => 'scheduled',
            'scheduled_at' => now()->addDay()->toDateTimeString(),
        ]);

        $futureResponse->assertStatus(201)
            ->assertJsonPath('data.status', 'scheduled');
    }

    public function test_cannot_publish_with_inactive_source_or_category(): void
    {
        $token = $this->createAdminToken(['news.create', 'news.publish']);

        $inactiveSource = NewsSource::create(['name' => 'Inactive Source', 'slug' => 'inactive-src', 'status' => false]);

        $response = $this->withToken($token)->postJson('/api/admin/news', [
            'news_source_id' => $inactiveSource->id,
            'news_category_id' => $this->category->id,
            'content_type' => 'news',
            'title' => 'Article with Inactive Source',
            'content' => '<p>Content</p>',
            'status' => 'published',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['news_source_id']);
    }

    public function test_admin_without_publish_permission_cannot_publish_article(): void
    {
        $token = $this->createAdminToken(['news.create']); // lacking news.publish

        $response = $this->withToken($token)->postJson('/api/admin/news', [
            'news_source_id' => $this->source->id,
            'news_category_id' => $this->category->id,
            'content_type' => 'news',
            'title' => 'Direct Publish Attempt',
            'content' => '<p>Content</p>',
            'status' => 'published',
        ]);

        $response->assertStatus(403);
    }

    public function test_archive_clears_featured_and_breaking_flags(): void
    {
        $token = $this->createAdminToken(['news.update']);

        $article = NewsArticle::create([
            'news_source_id' => $this->source->id,
            'news_category_id' => $this->category->id,
            'title' => 'Hot Story',
            'slug' => 'hot-story',
            'status' => 'published',
            'published_at' => now(),
            'is_featured' => true,
            'is_breaking' => true,
        ]);

        $response = $this->withToken($token)->patchJson("/api/admin/news/{$article->id}/status", [
            'status' => 'archived',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'archived')
            ->assertJsonPath('data.is_featured', false)
            ->assertJsonPath('data.is_breaking', false);

        $this->assertDatabaseHas('news_articles', [
            'id' => $article->id,
            'status' => 'archived',
            'is_featured' => false,
            'is_breaking' => false,
        ]);
    }

    public function test_admin_can_toggle_featured_and_breaking(): void
    {
        $token = $this->createAdminToken(['news.update']);

        $article = NewsArticle::create([
            'news_source_id' => $this->source->id,
            'news_category_id' => $this->category->id,
            'title' => 'Article',
            'slug' => 'article-toggle',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $featResponse = $this->withToken($token)->patchJson("/api/admin/news/{$article->id}/featured", ['is_featured' => true]);
        $featResponse->assertStatus(200)->assertJsonPath('data.is_featured', true);

        $brkResponse = $this->withToken($token)->patchJson("/api/admin/news/{$article->id}/breaking", ['is_breaking' => true]);
        $brkResponse->assertStatus(200)->assertJsonPath('data.is_breaking', true);
    }

    public function test_admin_can_soft_delete_article(): void
    {
        $token = $this->createAdminToken(['news.delete']);

        $article = NewsArticle::create([
            'news_source_id' => $this->source->id,
            'news_category_id' => $this->category->id,
            'title' => 'Delete Me',
            'slug' => 'delete-me',
            'status' => 'draft',
        ]);

        $response = $this->withToken($token)->deleteJson("/api/admin/news/{$article->id}");
        $response->assertStatus(200);

        $this->assertSoftDeleted('news_articles', ['id' => $article->id]);
    }
}
