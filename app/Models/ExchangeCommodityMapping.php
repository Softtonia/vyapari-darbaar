<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExchangeCommodityMapping extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'exchange_commodity_mappings';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'exchange_id',
        'commodity_id',
        'external_symbol',
        'external_code',
        'external_name',
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
        'exchange_id',
        'commodity_id',
        'external_symbol',
        'external_code',
        'external_name',
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
            'exchange_id' => 'integer',
            'commodity_id' => 'integer',
            'status' => 'boolean',
        ];
    }

    /**
     * Get the parent exchange.
     *
     * @return BelongsTo<Exchange, $this>
     */
    public function exchange(): BelongsTo
    {
        return $this->belongsTo(Exchange::class, 'exchange_id');
    }

    /**
     * Get the canonical internal commodity.
     *
     * @return BelongsTo<Commodity, $this>
     */
    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class, 'commodity_id');
    }

    /**
     * Get the derivative instruments/contracts under this mapping.
     *
     * @return HasMany<ExchangeInstrument, $this>
     */
    public function instruments(): HasMany
    {
        return $this->hasMany(ExchangeInstrument::class, 'exchange_commodity_mapping_id');
    }

    /**
     * Get the administrator who created the mapping.
     *
     * @return BelongsTo<Admin, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Get the administrator who last updated the mapping.
     *
     * @return BelongsTo<Admin, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by');
    }

    /**
     * Scope query to only include active mappings.
     *
     * @param  Builder<ExchangeCommodityMapping>  $query
     * @return Builder<ExchangeCommodityMapping>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    /**
     * Scope query to filter by status if supplied.
     *
     * @param  Builder<ExchangeCommodityMapping>  $query
     * @return Builder<ExchangeCommodityMapping>
     */
    public function scopeStatus(Builder $query, mixed $status): Builder
    {
        if ($status !== null && $status !== '') {
            $query->where('status', filter_var($status, FILTER_VALIDATE_BOOLEAN));
        }

        return $query;
    }

    /**
     * Scope query to filter by exchange ID.
     *
     * @param  Builder<ExchangeCommodityMapping>  $query
     * @return Builder<ExchangeCommodityMapping>
     */
    public function scopeExchange(Builder $query, mixed $exchangeId): Builder
    {
        if ($exchangeId !== null && $exchangeId !== '') {
            $query->where('exchange_id', (int) $exchangeId);
        }

        return $query;
    }

    /**
     * Scope query to filter by internal commodity ID.
     *
     * @param  Builder<ExchangeCommodityMapping>  $query
     * @return Builder<ExchangeCommodityMapping>
     */
    public function scopeCommodity(Builder $query, mixed $commodityId): Builder
    {
        if ($commodityId !== null && $commodityId !== '') {
            $query->where('commodity_id', (int) $commodityId);
        }

        return $query;
    }

    /**
     * Scope query to search by external symbol, code, or name.
     *
     * @param  Builder<ExchangeCommodityMapping>  $query
     * @return Builder<ExchangeCommodityMapping>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term !== '') {
            $query->where(function (Builder $q) use ($term) {
                $q->where('external_symbol', 'LIKE', "%{$term}%")
                    ->orWhere('external_code', 'LIKE', "%{$term}%")
                    ->orWhere('external_name', 'LIKE', "%{$term}%");
            });
        }

        return $query;
    }

    /**
     * Scope query to sort by a whitelisted column and order.
     *
     * @param  Builder<ExchangeCommodityMapping>  $query
     * @return Builder<ExchangeCommodityMapping>
     */
    public function scopeSorted(Builder $query, ?string $sortBy = 'id', ?string $sortOrder = 'desc'): Builder
    {
        $sortBy = strtolower(trim((string) $sortBy));
        $sortOrder = strtolower(trim((string) $sortOrder));

        if (! in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true)) {
            $sortBy = 'id';
        }

        if (! in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = 'desc';
        }

        $query->orderBy($sortBy, $sortOrder);

        if ($sortBy !== 'id') {
            $query->orderBy('id', 'desc');
        }

        return $query;
    }
}
