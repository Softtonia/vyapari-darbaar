<?php

namespace App\Models;

use App\Enums\IngestionSourceType;
use App\Enums\IngestionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketIngestionRun extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'market_ingestion_runs';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'exchange_id',
        'source_type',
        'trade_date',
        'source_file_name',
        'source_checksum',
        'storage_path',
        'status',
        'records_received',
        'records_inserted',
        'records_updated',
        'records_skipped',
        'records_failed',
        'started_at',
        'finished_at',
        'error_summary',
    ];

    /**
     * Allowed columns for dynamic sorting.
     *
     * @var list<string>
     */
    public const ALLOWED_SORT_COLUMNS = [
        'id',
        'exchange_id',
        'source_type',
        'trade_date',
        'status',
        'records_received',
        'records_inserted',
        'records_failed',
        'started_at',
        'finished_at',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exchange_id' => 'integer',
            'source_type' => IngestionSourceType::class,
            'status' => IngestionStatus::class,
            'trade_date' => 'date:Y-m-d',
            'records_received' => 'integer',
            'records_inserted' => 'integer',
            'records_updated' => 'integer',
            'records_skipped' => 'integer',
            'records_failed' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'error_summary' => 'array',
        ];
    }

    /**
     * Get the exchange this ingestion run belongs to.
     *
     * @return BelongsTo<Exchange, $this>
     */
    public function exchange(): BelongsTo
    {
        return $this->belongsTo(Exchange::class, 'exchange_id');
    }

    /**
     * Scope a query to a specific exchange.
     *
     * @param Builder<self> $query
     */
    public function scopeForExchange(Builder $query, int $exchangeId): Builder
    {
        return $query->where('exchange_id', $exchangeId);
    }

    /**
     * Scope a query to a specific source type.
     *
     * @param Builder<self> $query
     */
    public function scopeForSourceType(Builder $query, IngestionSourceType|string $sourceType): Builder
    {
        $value = $sourceType instanceof IngestionSourceType ? $sourceType->value : $sourceType;
        return $query->where('source_type', $value);
    }

    /**
     * Scope a query to a specific status.
     *
     * @param Builder<self> $query
     */
    public function scopeForStatus(Builder $query, IngestionStatus|string $status): Builder
    {
        $value = $status instanceof IngestionStatus ? $status->value : $status;
        return $query->where('status', $value);
    }
}
