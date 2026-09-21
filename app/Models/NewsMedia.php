<?php

namespace App\Models;

use App\Enums\NewsMediaType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class NewsMedia extends Model
{
    use HasFactory;

    protected $table = 'news_media';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'news_article_id',
        'media_type',
        'language',
        'title',
        'file_path',
        'external_url',
        'mime_type',
        'file_size',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'media_type' => NewsMediaType::class,
            'file_size' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the public URL for the media asset.
     */
    public function getUrlAttribute(): ?string
    {
        if (! empty($this->file_path)) {
            if (filter_var($this->file_path, FILTER_VALIDATE_URL)) {
                return $this->file_path;
            }

            return Storage::disk('public')->url($this->file_path);
        }

        return $this->external_url;
    }

    /**
     * Get the article this media belongs to.
     *
     * @return BelongsTo<NewsArticle, $this>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(NewsArticle::class, 'news_article_id');
    }
}
