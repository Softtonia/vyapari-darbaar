<?php

namespace App\Models;

use App\Enums\NewsImportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsImportRun extends Model
{
    use HasFactory;

    protected $table = 'news_import_runs';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'news_source_id',
        'news_category_id',
        'feed_url',
        'status',
        'items_received',
        'items_imported',
        'items_skipped',
        'items_failed',
        'started_at',
        'finished_at',
        'error_summary',
        'triggered_by',
    ];

    /**
     * Allowed columns for dynamic sorting in admin list.
     *
     * @var list<string>
     */
    public const ALLOWED_SORT_COLUMNS = [
        'id',
        'status',
        'items_received',
        'items_imported',
        'items_skipped',
        'items_failed',
        'started_at',
        'finished_at',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'news_source_id'   => 'integer',
            'news_category_id' => 'integer',
            'triggered_by'     => 'integer',
            'status'           => NewsImportStatus::class,
            'items_received'   => 'integer',
            'items_imported'   => 'integer',
            'items_skipped'    => 'integer',
            'items_failed'     => 'integer',
            'started_at'       => 'datetime',
            'finished_at'      => 'datetime',
            'error_summary'    => 'array',
        ];
    }

    /**
     * Get the NewsSource associated with this run.
     *
     * @return BelongsTo<NewsSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(NewsSource::class, 'news_source_id');
    }

    /**
     * Get the NewsCategory associated with this run.
     *
     * @return BelongsTo<NewsCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class, 'news_category_id');
    }

    /**
     * Get the Admin who triggered this run.
     *
     * @return BelongsTo<Admin, $this>
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'triggered_by');
    }

    /**
     * Scope to a specific status.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeForStatus(Builder $query, NewsImportStatus|string $status): Builder
    {
        $value = $status instanceof NewsImportStatus ? $status->value : $status;

        return $query->where('status', $value);
    }

    /**
     * Scope to a specific news source.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeForSource(Builder $query, int $sourceId): Builder
    {
        return $query->where('news_source_id', $sourceId);
    }
}
