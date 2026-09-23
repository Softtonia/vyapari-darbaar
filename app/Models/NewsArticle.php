<?php

namespace App\Models;

use App\Enums\NewsContentType;
use App\Enums\NewsStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class NewsArticle extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'news_articles';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'news_source_id',
        'news_category_id',
        'content_type',
        'title',
        'slug',
        'short_description',
        'content',
        'featured_image',
        'author_name',
        'source_url',
        'external_id',
        'imported_at',
        'published_on',
        'published_at',
        'scheduled_at',
        'status',
        'is_featured',
        'is_breaking',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'view_count',
        'created_by',
        'updated_by',
    ];

    /**
     * Allowed columns for dynamic sorting whitelist.
     *
     * @var list<string>
     */
    public const ALLOWED_SORT_COLUMNS = [
        'id',
        'title',
        'slug',
        'content_type',
        'status',
        'published_at',
        'published_on',
        'scheduled_at',
        'is_featured',
        'is_breaking',
        'view_count',
        'created_at',
        'updated_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'content_type'  => NewsContentType::class,
            'status'        => NewsStatus::class,
            'published_on'  => 'date:Y-m-d',
            'published_at'  => 'datetime',
            'scheduled_at'  => 'datetime',
            'imported_at'   => 'datetime',
            'is_featured'   => 'boolean',
            'is_breaking'   => 'boolean',
            'meta_keywords' => 'array',
            'view_count'    => 'integer',
        ];
    }

    /**
     * Get the fully qualified public URL for the featured image.
     */
    public function getFeaturedImageUrlAttribute(): ?string
    {
        if (empty($this->featured_image)) {
            return null;
        }

        if (filter_var($this->featured_image, FILTER_VALIDATE_URL)) {
            return $this->featured_image;
        }

        return Storage::disk('public')->url($this->featured_image);
    }

    /**
     * Get the source for this article.
     *
     * @return BelongsTo<NewsSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(NewsSource::class, 'news_source_id');
    }

    /**
     * Get the category for this article.
     *
     * @return BelongsTo<NewsCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class, 'news_category_id');
    }

    /**
     * Get media attachments for this article.
     *
     * @return HasMany<NewsMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(NewsMedia::class, 'news_article_id')->orderBy('sort_order', 'asc');
    }

    /**
     * Get the administrator who created the article.
     *
     * @return BelongsTo<Admin, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Get the administrator who last updated the article.
     *
     * @return BelongsTo<Admin, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by');
    }

    /**
     * Scope query to articles that are fully published and publicly visible.
     * Must have status published, published_at <= now, and active parent source and category.
     *
     * @param  Builder<NewsArticle>  $query
     * @return Builder<NewsArticle>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', NewsStatus::PUBLISHED->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereHas('source', fn (Builder $q) => $q->where('status', true))
            ->whereHas('category', fn (Builder $q) => $q->where('status', true));
    }

    /**
     * Scope query to scheduled articles due for publishing.
     *
     * @param  Builder<NewsArticle>  $query
     * @return Builder<NewsArticle>
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', NewsStatus::SCHEDULED->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now());
    }

    /**
     * Scope query to featured articles.
     *
     * @param  Builder<NewsArticle>  $query
     * @return Builder<NewsArticle>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope query to breaking articles.
     *
     * @param  Builder<NewsArticle>  $query
     * @return Builder<NewsArticle>
     */
    public function scopeBreaking(Builder $query): Builder
    {
        return $query->where('is_breaking', true);
    }

    /**
     * Scope query by news source id.
     *
     * @param  Builder<NewsArticle>  $query
     * @param  mixed  $sourceId
     * @return Builder<NewsArticle>
     */
    public function scopeBySource(Builder $query, mixed $sourceId): Builder
    {
        if ($sourceId !== null && $sourceId !== '') {
            return $query->where('news_source_id', (int) $sourceId);
        }

        return $query;
    }

    /**
     * Scope query by news category id.
     *
     * @param  Builder<NewsArticle>  $query
     * @param  mixed  $categoryId
     * @return Builder<NewsArticle>
     */
    public function scopeByCategory(Builder $query, mixed $categoryId): Builder
    {
        if ($categoryId !== null && $categoryId !== '') {
            return $query->where('news_category_id', (int) $categoryId);
        }

        return $query;
    }

    /**
     * Scope query by content type.
     *
     * @param  Builder<NewsArticle>  $query
     * @param  mixed  $contentType
     * @return Builder<NewsArticle>
     */
    public function scopeByContentType(Builder $query, mixed $contentType): Builder
    {
        if ($contentType instanceof NewsContentType) {
            return $query->where('content_type', $contentType->value);
        }

        if (! empty($contentType)) {
            return $query->where('content_type', (string) $contentType);
        }

        return $query;
    }

    /**
     * Scope query by publication date range.
     *
     * @param  Builder<NewsArticle>  $query
     * @param  string|null  $from
     * @param  string|null  $to
     * @return Builder<NewsArticle>
     */
    public function scopePublishedBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        if (! empty($from)) {
            $query->where('published_at', '>=', $from);
        }

        if (! empty($to)) {
            $query->where('published_at', '<=', $to);
        }

        return $query;
    }

    /**
     * Scope query for search keyword across title and short_description.
     *
     * @param  Builder<NewsArticle>  $query
     * @param  string|null  $term
     * @return Builder<NewsArticle>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! empty($term)) {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($term));
            $query->where(function (Builder $sub) use ($escaped) {
                $sub->where('title', 'like', "%{$escaped}%")
                    ->orWhere('short_description', 'like', "%{$escaped}%")
                    ->orWhere('slug', 'like', "%{$escaped}%");
            });
        }

        return $query;
    }
}
