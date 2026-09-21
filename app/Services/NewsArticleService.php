<?php

namespace App\Services;

use App\Enums\NewsContentType;
use App\Enums\NewsStatus;
use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\NewsSource;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NewsArticleService
{
    public const FEATURED_CACHE_KEY = 'news:featured';
    public const FEATURED_CACHE_TTL = 900; // 15 mins

    public const BREAKING_CACHE_KEY = 'news:breaking';
    public const BREAKING_CACHE_TTL = 300; // 5 mins

    public const DETAIL_CACHE_PREFIX = 'news:detail:';
    public const DETAIL_CACHE_TTL = 1800; // 30 mins

    public const STORAGE_DISK = 'public';

    public function __construct(
        protected HtmlSanitizerService $sanitizer
    ) {}

    /**
     * List news articles with pagination and filtering for admin.
     * Excludes longtext content for performance.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listAdminArticles(array $filters): LengthAwarePaginator
    {
        $query = NewsArticle::query()
            ->select([
                'id',
                'news_source_id',
                'news_category_id',
                'content_type',
                'title',
                'slug',
                'short_description',
                'featured_image',
                'author_name',
                'source_url',
                'published_on',
                'published_at',
                'scheduled_at',
                'status',
                'is_featured',
                'is_breaking',
                'view_count',
                'created_by',
                'updated_by',
                'created_at',
                'updated_at',
            ])
            ->with([
                'source:id,name,slug,logo,status',
                'category:id,name,slug,status',
            ]);

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['content_type'])) {
            $query->byContentType($filters['content_type']);
        }

        if (! empty($filters['news_source_id'])) {
            $query->bySource($filters['news_source_id']);
        }

        if (! empty($filters['news_category_id'])) {
            $query->byCategory($filters['news_category_id']);
        }

        if (array_key_exists('is_featured', $filters) && $filters['is_featured'] !== null) {
            $query->where('is_featured', (bool) $filters['is_featured']);
        }

        if (array_key_exists('is_breaking', $filters) && $filters['is_breaking'] !== null) {
            $query->where('is_breaking', (bool) $filters['is_breaking']);
        }

        if (! empty($filters['published_from']) || ! empty($filters['published_to'])) {
            $query->publishedBetween($filters['published_from'] ?? null, $filters['published_to'] ?? null);
        }

        if (! empty($filters['created_from'])) {
            $query->where('created_at', '>=', $filters['created_from']);
        }

        if (! empty($filters['created_to'])) {
            $query->where('created_at', '<=', $filters['created_to']);
        }

        $sortBy = $filters['sort_by'] ?? 'id';
        $sortOrder = strtolower($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $sortOrder);

        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return $query->paginate($perPage);
    }

    /**
     * List publicly visible news articles.
     * Excludes longtext content and loads only public essential data.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listPublicArticles(array $filters): LengthAwarePaginator
    {
        $query = NewsArticle::query()
            ->published()
            ->select([
                'id',
                'news_source_id',
                'news_category_id',
                'content_type',
                'title',
                'slug',
                'short_description',
                'featured_image',
                'author_name',
                'source_url',
                'published_on',
                'published_at',
                'is_featured',
                'is_breaking',
                'view_count',
            ])
            ->with([
                'source:id,name,slug,logo',
                'category:id,name,slug',
            ]);

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        $categoryId = $filters['category_id'] ?? $filters['news_category_id'] ?? null;
        if (! empty($categoryId)) {
            $query->byCategory($categoryId);
        }

        $sourceId = $filters['source_id'] ?? $filters['news_source_id'] ?? null;
        if (! empty($sourceId)) {
            $query->bySource($sourceId);
        }

        if (! empty($filters['content_type'])) {
            $query->byContentType($filters['content_type']);
        }

        $featured = $filters['featured'] ?? $filters['is_featured'] ?? null;
        if ($featured !== null) {
            $query->where('is_featured', (bool) $featured);
        }

        $breaking = $filters['breaking'] ?? $filters['is_breaking'] ?? null;
        if ($breaking !== null) {
            $query->where('is_breaking', (bool) $breaking);
        }

        if (! empty($filters['published_from']) || ! empty($filters['published_to'])) {
            $query->publishedBetween($filters['published_from'] ?? null, $filters['published_to'] ?? null);
        }

        // Default sort: latest published first
        $query->orderBy('published_at', 'desc')->orderBy('id', 'desc');

        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return $query->paginate($perPage);
    }

    /**
     * Get a publicly visible article by slug, with media and relations.
     * Safely increments view count atomically.
     */
    public function getPublicArticleBySlug(string $slug): ?NewsArticle
    {
        $cacheKey = self::DETAIL_CACHE_PREFIX . $slug;

        $article = Cache::remember($cacheKey, self::DETAIL_CACHE_TTL, function () use ($slug) {
            return NewsArticle::query()
                ->published()
                ->where('slug', $slug)
                ->with([
                    'source:id,name,slug,code,website_url,logo,description',
                    'category:id,name,slug,description',
                    'media',
                ])
                ->first();
        });

        if ($article) {
            // Atomic non-blocking view count increment in database
            NewsArticle::where('id', $article->id)->increment('view_count');
            $article->view_count += 1;
        }

        return $article;
    }

    /**
     * Get featured news articles (cached).
     *
     * @return Collection<int, NewsArticle>
     */
    public function getFeaturedArticles(int $limit = 5): Collection
    {
        return Cache::remember(self::FEATURED_CACHE_KEY, self::FEATURED_CACHE_TTL, function () use ($limit) {
            return NewsArticle::query()
                ->published()
                ->featured()
                ->select([
                    'id',
                    'news_source_id',
                    'news_category_id',
                    'content_type',
                    'title',
                    'slug',
                    'short_description',
                    'featured_image',
                    'author_name',
                    'published_on',
                    'published_at',
                    'is_featured',
                    'is_breaking',
                    'view_count',
                ])
                ->with([
                    'source:id,name,slug,logo',
                    'category:id,name,slug',
                ])
                ->orderBy('published_at', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get breaking news articles (cached).
     *
     * @return Collection<int, NewsArticle>
     */
    public function getBreakingNews(int $limit = 5): Collection
    {
        return Cache::remember(self::BREAKING_CACHE_KEY, self::BREAKING_CACHE_TTL, function () use ($limit) {
            return NewsArticle::query()
                ->published()
                ->breaking()
                ->select([
                    'id',
                    'news_source_id',
                    'news_category_id',
                    'content_type',
                    'title',
                    'slug',
                    'short_description',
                    'featured_image',
                    'author_name',
                    'published_on',
                    'published_at',
                    'is_featured',
                    'is_breaking',
                    'view_count',
                ])
                ->with([
                    'source:id,name,slug,logo',
                    'category:id,name,slug',
                ])
                ->orderBy('published_at', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Create a new news article.
     *
     * @param  array<string, mixed>  $data
     */
    public function createArticle(array $data, ?int $adminId = null): NewsArticle
    {
        $status = $data['status'] instanceof NewsStatus ? $data['status']->value : ($data['status'] ?? NewsStatus::DRAFT->value);

        // Sanitize rich text HTML content
        if (isset($data['content'])) {
            $data['content'] = $this->sanitizer->sanitize($data['content']);
        }

        // Generate unique slug
        $slug = ! empty($data['slug']) ? Str::slug($data['slug']) : $this->generateSlug($data['title']);

        // Handle publishing timestamp defaults
        $publishedAt = $data['published_at'] ?? null;
        $publishedOn = $data['published_on'] ?? null;

        if ($status === NewsStatus::PUBLISHED->value) {
            $this->validatePublishableParents((int) $data['news_source_id'], (int) $data['news_category_id']);
            $publishedAt = $publishedAt ?: now();
            $publishedOn = $publishedOn ?: now()->toDateString();
        }

        // Handle featured image upload
        $imagePath = null;
        if (isset($data['featured_image']) && $data['featured_image'] instanceof UploadedFile) {
            $imagePath = $data['featured_image']->store('news/featured', self::STORAGE_DISK);
        } elseif (isset($data['featured_image']) && is_string($data['featured_image'])) {
            $imagePath = $data['featured_image'];
        }

        $article = NewsArticle::create([
            'news_source_id' => $data['news_source_id'],
            'news_category_id' => $data['news_category_id'],
            'content_type' => $data['content_type'],
            'title' => $data['title'],
            'slug' => $slug,
            'short_description' => $data['short_description'] ?? null,
            'content' => $data['content'] ?? null,
            'featured_image' => $imagePath,
            'author_name' => $data['author_name'] ?? null,
            'source_url' => $data['source_url'] ?? null,
            'published_on' => $publishedOn,
            'published_at' => $publishedAt,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'status' => $status,
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'is_breaking' => (bool) ($data['is_breaking'] ?? false),
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'meta_keywords' => $data['meta_keywords'] ?? null,
            'created_by' => $adminId,
            'updated_by' => $adminId,
        ]);

        $this->invalidateCaches($article);

        return $article->load(['source', 'category']);
    }

    /**
     * Update an existing news article.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateArticle(NewsArticle $article, array $data, ?int $adminId = null): NewsArticle
    {
        $oldSlug = $article->slug;
        $oldImage = $article->featured_image;

        if (array_key_exists('content', $data)) {
            $data['content'] = $this->sanitizer->sanitize($data['content']);
        }

        if (array_key_exists('title', $data) || array_key_exists('slug', $data)) {
            if (! empty($data['slug'])) {
                $data['slug'] = Str::slug($data['slug']);
            } elseif (array_key_exists('title', $data) && $data['title'] !== $article->title && empty($article->slug)) {
                $data['slug'] = $this->generateSlug($data['title'], $article->id);
            }
        }

        // Status transition handling
        if (isset($data['status'])) {
            $statusVal = $data['status'] instanceof NewsStatus ? $data['status']->value : (string) $data['status'];
            if ($statusVal === NewsStatus::PUBLISHED->value) {
                $sourceId = (int) ($data['news_source_id'] ?? $article->news_source_id);
                $categoryId = (int) ($data['news_category_id'] ?? $article->news_category_id);
                $this->validatePublishableParents($sourceId, $categoryId);

                if (empty($article->published_at) && empty($data['published_at'])) {
                    $data['published_at'] = now();
                }
                if (empty($article->published_on) && empty($data['published_on'])) {
                    $data['published_on'] = now()->toDateString();
                }
            } elseif ($statusVal === NewsStatus::ARCHIVED->value) {
                // Clear featured and breaking flags on archive
                $data['is_featured'] = false;
                $data['is_breaking'] = false;
            }
        }

        // Handle featured image replacement
        if (isset($data['featured_image']) && $data['featured_image'] instanceof UploadedFile) {
            $data['featured_image'] = $data['featured_image']->store('news/featured', self::STORAGE_DISK);
            if ($oldImage && ! filter_var($oldImage, FILTER_VALIDATE_URL)) {
                Storage::disk(self::STORAGE_DISK)->delete($oldImage);
            }
        }

        $data['updated_by'] = $adminId;

        $article->update($data);

        $this->invalidateCaches($article, $oldSlug);

        return $article->fresh()->load(['source', 'category', 'media']);
    }

    /**
     * Update the status of an article.
     */
    public function updateStatus(NewsArticle $article, string $status, ?int $adminId = null, ?string $scheduledAt = null, ?string $publishedAt = null): NewsArticle
    {
        $oldSlug = $article->slug;
        $payload = [
            'status' => $status,
            'updated_by' => $adminId,
        ];

        if ($status === NewsStatus::PUBLISHED->value) {
            $this->validatePublishableParents($article->news_source_id, $article->news_category_id);

            $payload['published_at'] = $publishedAt ?: ($article->published_at ?: now());
            $payload['published_on'] = $article->published_on ?: now()->toDateString();
        } elseif ($status === NewsStatus::SCHEDULED->value) {
            if ($scheduledAt) {
                $payload['scheduled_at'] = $scheduledAt;
            }
        } elseif ($status === NewsStatus::ARCHIVED->value) {
            // Clear featured and breaking flags on archive
            $payload['is_featured'] = false;
            $payload['is_breaking'] = false;
        }

        $article->update($payload);

        $this->invalidateCaches($article, $oldSlug);

        return $article->fresh();
    }

    /**
     * Toggle or set featured status.
     */
    public function updateFeatured(NewsArticle $article, bool $isFeatured, ?int $adminId = null): NewsArticle
    {
        $article->update([
            'is_featured' => $isFeatured,
            'updated_by' => $adminId,
        ]);

        $this->invalidateCaches($article);

        return $article->fresh();
    }

    /**
     * Toggle or set breaking status.
     */
    public function updateBreaking(NewsArticle $article, bool $isBreaking, ?int $adminId = null): NewsArticle
    {
        $article->update([
            'is_breaking' => $isBreaking,
            'updated_by' => $adminId,
        ]);

        $this->invalidateCaches($article);

        return $article->fresh();
    }

    /**
     * Soft delete an article and invalidate caches.
     */
    public function deleteArticle(NewsArticle $article): void
    {
        $this->invalidateCaches($article);
        $article->delete();
    }

    /**
     * Invalidate all related Redis caches for public news.
     */
    public function invalidateCaches(?NewsArticle $article = null, ?string $oldSlug = null): void
    {
        Cache::forget(self::FEATURED_CACHE_KEY);
        Cache::forget(self::BREAKING_CACHE_KEY);

        if ($article && ! empty($article->slug)) {
            Cache::forget(self::DETAIL_CACHE_PREFIX . $article->slug);
        }

        if ($oldSlug && $oldSlug !== ($article?->slug)) {
            Cache::forget(self::DETAIL_CACHE_PREFIX . $oldSlug);
        }
    }

    /**
     * Ensure the source and category are active prior to publishing.
     *
     * @throws DomainException
     */
    protected function validatePublishableParents(int $sourceId, int $categoryId): void
    {
        $source = NewsSource::find($sourceId);
        if (! $source || ! $source->status) {
            throw new DomainException('Cannot publish article because the associated news source is inactive or deleted.');
        }

        $category = NewsCategory::find($categoryId);
        if (! $category || ! $category->status) {
            throw new DomainException('Cannot publish article because the associated news category is inactive or deleted.');
        }
    }

    /**
     * Generate unique slug for news article.
     */
    public function generateSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        $base = $base !== '' ? $base : 'news-article';
        $slug = $base;
        $counter = 1;

        while (NewsArticle::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $counter++;
            $slug = "{$base}-{$counter}";
        }

        return $slug;
    }
}
