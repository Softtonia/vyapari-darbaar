<?php

namespace Tests\Feature\News;

use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\NewsSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_news_categories(): void
    {
        $token = $this->createAdminToken(['news-categories.view']);

        NewsCategory::create(['name' => 'Cat 1', 'slug' => 'cat-1']);
        NewsCategory::create(['name' => 'Cat 2', 'slug' => 'cat-2']);

        $response = $this->withToken($token)->getJson('/api/admin/news-categories');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.items');
    }

    public function test_admin_can_create_news_category(): void
    {
        $token = $this->createAdminToken(['news-categories.create']);

        $response = $this->withToken($token)->postJson('/api/admin/news-categories', [
            'name' => 'Market Insights',
            'description' => 'In-depth market insights',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Market Insights')
            ->assertJsonPath('data.slug', 'market-insights');

        $this->assertDatabaseHas('news_categories', [
            'name' => 'Market Insights',
            'slug' => 'market-insights',
        ]);
    }

    public function test_admin_can_update_news_category(): void
    {
        $token = $this->createAdminToken(['news-categories.update']);

        $category = NewsCategory::create(['name' => 'Old Cat', 'slug' => 'old-cat']);

        $response = $this->withToken($token)->patchJson("/api/admin/news-categories/{$category->id}", [
            'name' => 'New Cat',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Cat');
    }

    public function test_category_options_cached(): void
    {
        $token = $this->createAdminToken(['news-categories.view']);

        NewsCategory::create(['name' => 'Active Cat', 'slug' => 'active-cat', 'status' => true]);
        NewsCategory::create(['name' => 'Inactive Cat', 'slug' => 'inactive-cat', 'status' => false]);

        $response = $this->withToken($token)->getJson('/api/admin/news-categories/options');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'active-cat');
    }

    public function test_admin_can_update_category_status(): void
    {
        $token = $this->createAdminToken(['news-categories.update']);

        $category = NewsCategory::create(['name' => 'Category', 'slug' => 'cat', 'status' => true]);

        $response = $this->withToken($token)->patchJson("/api/admin/news-categories/{$category->id}/status", [
            'status' => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', false);
    }

    public function test_referenced_category_cannot_be_deleted(): void
    {
        $token = $this->createAdminToken(['news-categories.delete']);

        $source = NewsSource::create(['name' => 'Source', 'slug' => 'source']);
        $category = NewsCategory::create(['name' => 'Category', 'slug' => 'category']);

        NewsArticle::create([
            'news_source_id' => $source->id,
            'news_category_id' => $category->id,
            'title' => 'Article',
            'slug' => 'article',
            'status' => 'draft',
        ]);

        $response = $this->withToken($token)->deleteJson("/api/admin/news-categories/{$category->id}");

        $response->assertStatus(409)
            ->assertJson([
                'status' => false,
                'error' => 'CATEGORY_IN_USE',
            ]);
    }

    public function test_unused_category_soft_deletes(): void
    {
        $token = $this->createAdminToken(['news-categories.delete']);

        $category = NewsCategory::create(['name' => 'Unused Cat', 'slug' => 'unused-cat']);

        $response = $this->withToken($token)->deleteJson("/api/admin/news-categories/{$category->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('news_categories', ['id' => $category->id]);
    }

    public function test_permission_enforcement_on_news_categories(): void
    {
        $unauthorizedToken = $this->createAdminToken([]);

        $response = $this->withToken($unauthorizedToken)->getJson('/api/admin/news-categories');
        $response->assertStatus(403);
    }
}
