<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class NewsSource extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'news_sources';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'code',
        'website_url',
        'logo',
        'description',
        'sort_order',
        'status',
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
        'name',
        'slug',
        'code',
        'sort_order',
        'status',
        'created_at',
        'updated_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the fully qualified public URL for the source logo.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (empty($this->logo)) {
            return null;
        }

        if (filter_var($this->logo, FILTER_VALIDATE_URL)) {
            return $this->logo;
        }

        return Storage::disk('public')->url($this->logo);
    }

    /**
     * Get articles from this news source.
     *
     * @return HasMany<NewsArticle, $this>
     */
    public function articles(): HasMany
    {
        return $this->hasMany(NewsArticle::class, 'news_source_id');
    }

    /**
     * Get the administrator who created the source.
     *
     * @return BelongsTo<Admin, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Get the administrator who last updated the source.
     *
     * @return BelongsTo<Admin, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by');
    }

    /**
     * Scope query to active sources.
     *
     * @param  Builder<NewsSource>  $query
     * @return Builder<NewsSource>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    /**
     * Scope query to default ordered sources.
     *
     * @param  Builder<NewsSource>  $query
     * @return Builder<NewsSource>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('id', 'asc');
    }

    /**
     * Scope query for search keyword.
     *
     * @param  Builder<NewsSource>  $query
     * @param  string|null  $term
     * @return Builder<NewsSource>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! empty($term)) {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($term));
            $query->where(function (Builder $sub) use ($escaped) {
                $sub->where('name', 'like', "%{$escaped}%")
                    ->orWhere('code', 'like', "%{$escaped}%")
                    ->orWhere('slug', 'like', "%{$escaped}%");
            });
        }

        return $query;
    }
}
