<?php

namespace Tests\Feature\News;

use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\NewsSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NewsSourceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_news_sources(): void
    {
        $token = $this->createAdminToken(['news-sources.view']);

        NewsSource::create(['name' => 'Source A', 'slug' => 'source-a', 'sort_order' => 1]);
        NewsSource::create(['name' => 'Source B', 'slug' => 'source-b', 'sort_order' => 2]);

        $response = $this->withToken($token)->getJson('/api/admin/news-sources');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'News sources fetched successfully.',
            ])
            ->assertJsonCount(2, 'data.items');
    }

    public function test_admin_can_create_news_source_with_generated_slug(): void
    {
        $token = $this->createAdminToken(['news-sources.create']);

        $response = $this->withToken($token)->postJson('/api/admin/news-sources', [
            'name' => 'NCDEX Press',
            'code' => 'NCDEX_P',
            'website_url' => 'https://ncdex.com',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => true,
                'data' => [
                    'name' => 'NCDEX Press',
                    'slug' => 'ncdex-press',
                    'code' => 'NCDEX_P',
                ],
            ]);

        $this->assertDatabaseHas('news_sources', [
            'name' => 'NCDEX Press',
            'slug' => 'ncdex-press',
        ]);
    }

    public function test_duplicate_source_slug_is_rejected(): void
    {
        $token = $this->createAdminToken(['news-sources.create']);

        NewsSource::create(['name' => 'Existing Source', 'slug' => 'duplicate-slug']);

        $response = $this->withToken($token)->postJson('/api/admin/news-sources', [
            'name' => 'Another Source',
            'slug' => 'duplicate-slug',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_options_returns_only_active_sources(): void
    {
        $token = $this->createAdminToken(['news-sources.view']);

        NewsSource::create(['name' => 'Active Source', 'slug' => 'active-src', 'status' => true]);
        NewsSource::create(['name' => 'Inactive Source', 'slug' => 'inactive-src', 'status' => false]);

        $response = $this->withToken($token)->getJson('/api/admin/news-sources/options');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'active-src');
    }

    public function test_admin_can_update_news_source(): void
    {
        $token = $this->createAdminToken(['news-sources.update']);

        $source = NewsSource::create(['name' => 'Old Name', 'slug' => 'old-name']);

        $response = $this->withToken($token)->patchJson("/api/admin/news-sources/{$source->id}", [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('news_sources', [
            'id' => $source->id,
            'name' => 'New Name',
        ]);
    }

    public function test_admin_can_update_news_source_status(): void
    {
        $token = $this->createAdminToken(['news-sources.update']);

        $source = NewsSource::create(['name' => 'Source', 'slug' => 'source', 'status' => true]);

        $response = $this->withToken($token)->patchJson("/api/admin/news-sources/{$source->id}/status", [
            'status' => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', false);

        $this->assertDatabaseHas('news_sources', [
            'id' => $source->id,
            'status' => false,
        ]);
    }

    public function test_referenced_news_source_cannot_be_deleted(): void
    {
        $token = $this->createAdminToken(['news-sources.delete']);

        $source = NewsSource::create(['name' => 'Source', 'slug' => 'source']);
        $category = NewsCategory::create(['name' => 'Category', 'slug' => 'category']);

        NewsArticle::create([
            'news_source_id' => $source->id,
            'news_category_id' => $category->id,
            'title' => 'Sample Article',
            'slug' => 'sample-article',
            'status' => 'draft',
        ]);

        $response = $this->withToken($token)->deleteJson("/api/admin/news-sources/{$source->id}");

        $response->assertStatus(409)
            ->assertJson([
                'status' => false,
                'error' => 'SOURCE_IN_USE',
            ]);

        $this->assertDatabaseHas('news_sources', ['id' => $source->id, 'deleted_at' => null]);
    }

    public function test_unused_news_source_soft_deletes(): void
    {
        $token = $this->createAdminToken(['news-sources.delete']);

        $source = NewsSource::create(['name' => 'Unused Source', 'slug' => 'unused-source']);

        $response = $this->withToken($token)->deleteJson("/api/admin/news-sources/{$source->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('news_sources', ['id' => $source->id]);
    }

    public function test_permission_enforcement_on_news_sources(): void
    {
        $token = $this->createAdminToken([], []); // no permissions and no admin role

        $response = $this->withToken($token)->getJson('/api/admin/news-sources');
        $response->assertStatus(403);
    }
}
