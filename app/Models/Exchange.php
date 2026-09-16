<?php

namespace App\Models;

use App\Enums\ExchangeType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exchange extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'exchanges';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'code',
        'exchange_type',
        'timezone',
        'website',
        'default_data_delay_minutes',
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
        'exchange_type',
        'default_data_delay_minutes',
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
            'exchange_type' => ExchangeType::class,
            'default_data_delay_minutes' => 'integer',
            'sort_order' => 'integer',
            'status' => 'boolean',
        ];
    }

    /**
     * Get the commodity mappings configured for this exchange.
     *
     * @return HasMany<ExchangeCommodityMapping, $this>
     */
    public function mappings(): HasMany
    {
        return $this->hasMany(ExchangeCommodityMapping::class, 'exchange_id');
    }

    /**
     * Get all instruments belonging to this exchange.
     *
     * @return HasMany<ExchangeInstrument, $this>
     */
    public function instruments(): HasMany
    {
        return $this->hasMany(ExchangeInstrument::class, 'exchange_id');
    }

    /**
     * Get all ingestion runs for this exchange.
     *
     * @return HasMany<MarketIngestionRun, $this>
     */
    public function ingestionRuns(): HasMany
    {
        return $this->hasMany(MarketIngestionRun::class, 'exchange_id');
    }

    /**
     * Get the administrator who created the exchange.
     *
     * @return BelongsTo<Admin, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Get the administrator who last updated the exchange.
     *
     * @return BelongsTo<Admin, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by');
    }

    /**
     * Scope query to only include active exchanges.
     *
     * @param  Builder<Exchange>  $query
     * @return Builder<Exchange>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    /**
     * Scope query to filter by status if supplied.
     *
     * @param  Builder<Exchange>  $query
     * @return Builder<Exchange>
     */
    public function scopeStatus(Builder $query, mixed $status): Builder
    {
        if ($status !== null && $status !== '') {
            $query->where('status', filter_var($status, FILTER_VALIDATE_BOOLEAN));
        }

        return $query;
    }

    /**
     * Scope query to search by name or code.
     *
     * @param  Builder<Exchange>  $query
     * @return Builder<Exchange>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term !== '') {
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                    ->orWhere('code', 'LIKE', "%{$term}%")
                    ->orWhere('slug', 'LIKE', "%{$term}%");
            });
        }

        return $query;
    }

    /**
     * Scope query to sort by a whitelisted column and order.
     *
     * @param  Builder<Exchange>  $query
     * @return Builder<Exchange>
     */
    public function scopeSorted(Builder $query, ?string $sortBy = 'sort_order', ?string $sortOrder = 'asc'): Builder
    {
        $sortBy = strtolower(trim((string) $sortBy));
        $sortOrder = strtolower(trim((string) $sortOrder));

        if (! in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true)) {
            $sortBy = 'sort_order';
        }

        if (! in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = 'asc';
        }

        $query->orderBy($sortBy, $sortOrder);

        if ($sortBy !== 'id') {
            $query->orderBy('id', 'desc');
        }

        return $query;
    }
}
