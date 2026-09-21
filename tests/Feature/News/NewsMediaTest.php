<?php

namespace Tests\Feature\News;

use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\NewsMedia;
use App\Models\NewsSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsMediaTest extends TestCase
{
    use RefreshDatabase;

    protected NewsArticle $article;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $source = NewsSource::create(['name' => 'Source', 'slug' => 'src', 'status' => true]);
        $category = NewsCategory::create(['name' => 'Category', 'slug' => 'cat', 'status' => true]);

        $this->article = NewsArticle::create([
            'news_source_id' => $source->id,
            'news_category_id' => $category->id,
            'title' => 'Article with Media',
            'slug' => 'article-media',
            'status' => 'draft',
        ]);
    }

    public function test_admin_can_upload_image_media(): void
    {
        $token = $this->createAdminToken(['news.update']);

        $file = UploadedFile::fake()->image('chart.png', 400, 300);

        $response = $this->withToken($token)->postJson("/api/admin/news/{$this->article->id}/media", [
            'media_type' => 'image',
            'title' => 'Price Chart',
            'file' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Price Chart')
            ->assertJsonPath('data.media_type', 'image');

        $this->assertDatabaseHas('news_media', [
            'news_article_id' => $this->article->id,
            'title' => 'Price Chart',
            'media_type' => 'image',
        ]);
    }

    public function test_admin_can_upload_pdf_media(): void
    {
        $token = $this->createAdminToken(['news.update']);

        $file = UploadedFile::fake()->create('report.pdf', 500, 'application/pdf');

        $response = $this->withToken($token)->postJson("/api/admin/news/{$this->article->id}/media", [
            'media_type' => 'pdf',
            'title' => 'Annual Report',
            'file' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Annual Report')
            ->assertJsonPath('data.media_type', 'pdf');
    }

    public function test_admin_can_add_external_link_media(): void
    {
        $token = $this->createAdminToken(['news.update']);

        $response = $this->withToken($token)->postJson("/api/admin/news/{$this->article->id}/media", [
            'media_type' => 'external_link',
            'title' => 'Official Circular',
            'external_url' => 'https://ncdex.com/circulars/123.pdf',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.url', 'https://ncdex.com/circulars/123.pdf');
    }

    public function test_cross_article_media_delete_is_blocked(): void
    {
        $token = $this->createAdminToken(['news.update']);

        $otherArticle = NewsArticle::create([
            'news_source_id' => $this->article->news_source_id,
            'news_category_id' => $this->article->news_category_id,
            'title' => 'Other Article',
            'slug' => 'other-article',
            'status' => 'draft',
        ]);

        $media = NewsMedia::create([
            'news_article_id' => $otherArticle->id,
            'media_type' => 'external_link',
            'title' => 'Other Media',
            'external_url' => 'https://example.com',
        ]);

        // Attempt to delete through wrong article endpoint
        $response = $this->withToken($token)->deleteJson("/api/admin/news/{$this->article->id}/media/{$media->id}");

        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
            ]);

        $this->assertDatabaseHas('news_media', ['id' => $media->id]);
    }

    public function test_media_delete_removes_physical_file_safely(): void
    {
        $token = $this->createAdminToken(['news.update']);

        $file = UploadedFile::fake()->image('photo.jpg');
        $storedPath = $file->store("news/{$this->article->id}/images", 'public');

        $media = NewsMedia::create([
            'news_article_id' => $this->article->id,
            'media_type' => 'image',
            'file_path' => $storedPath,
        ]);

        Storage::disk('public')->assertExists($storedPath);

        $response = $this->withToken($token)->deleteJson("/api/admin/news/{$this->article->id}/media/{$media->id}");
        $response->assertStatus(200);

        $this->assertDatabaseMissing('news_media', ['id' => $media->id]);
        Storage::disk('public')->assertMissing($storedPath);
    }
}
