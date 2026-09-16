<?php

namespace App\Models;

use App\Enums\InstrumentLifecycleStatus;
use App\Enums\InstrumentType;
use App\Enums\OptionType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeInstrument extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'exchange_instruments';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'exchange_id',
        'exchange_commodity_mapping_id',
        'external_instrument_id',
        'symbol',
        'instrument_name',
        'instrument_type',
        'original_expiry_date',
        'actual_expiry_date',
        'strike_price',
        'option_type',
        'lot_size',
        'tick_size',
        'quote_unit',
        'contract_unit',
        'lifecycle_status',
        'is_enabled',
        'listed_at',
        'delisted_at',
    ];

    /**
     * Allowed columns for dynamic sorting whitelist.
     *
     * @var list<string>
     */
    public const ALLOWED_SORT_COLUMNS = [
        'id',
        'exchange_id',
        'exchange_commodity_mapping_id',
        'external_instrument_id',
        'symbol',
        'instrument_type',
        'actual_expiry_date',
        'strike_price',
        'option_type',
        'lot_size',
        'tick_size',
        'lifecycle_status',
        'is_enabled',
        'listed_at',
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
            'exchange_commodity_mapping_id' => 'integer',
            'instrument_type' => InstrumentType::class,
            'option_type' => OptionType::class,
            'lifecycle_status' => InstrumentLifecycleStatus::class,
            'is_enabled' => 'boolean',
            'original_expiry_date' => 'date:Y-m-d',
            'actual_expiry_date' => 'date:Y-m-d',
            'strike_price' => 'decimal:8',
            'lot_size' => 'decimal:6',
            'tick_size' => 'decimal:8',
            'listed_at' => 'datetime',
            'delisted_at' => 'datetime',
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
     * Get the parent commodity mapping.
     *
     * @return BelongsTo<ExchangeCommodityMapping, $this>
     */
    public function mapping(): BelongsTo
    {
        return $this->belongsTo(ExchangeCommodityMapping::class, 'exchange_commodity_mapping_id');
    }

    /**
     * Get the historical market bhavcopies for this instrument.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<MarketBhavcopy, $this>
     */
    public function bhavcopies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MarketBhavcopy::class, 'exchange_instrument_id');
    }

    /**
     * Derived attribute for contract month representation (e.g. 2026-10).
     */
    public function getContractMonthAttribute(): ?string
    {
        $date = $this->actual_expiry_date ?? $this->original_expiry_date;
        if (! $date) {
            return null;
        }

        return Carbon::parse($date)->format('Y-m');
    }

    /**
     * Scope query to only include locally enabled instruments.
     *
     * @param  Builder<ExchangeInstrument>  $query
     * @return Builder<ExchangeInstrument>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    /**
     * Scope query to only include active lifecycle contracts.
     *
     * @param  Builder<ExchangeInstrument>  $query
     * @return Builder<ExchangeInstrument>
     */
    public function scopeActiveLifecycle(Builder $query): Builder
    {
        return $query->where('lifecycle_status', InstrumentLifecycleStatus::ACTIVE->value);
    }

    /**
     * Scope query to filter by exchange ID.
     *
     * @param  Builder<ExchangeInstrument>  $query
     * @return Builder<ExchangeInstrument>
     */
    public function scopeExchange(Builder $query, mixed $exchangeId): Builder
    {
        if ($exchangeId !== null && $exchangeId !== '') {
            $query->where('exchange_id', (int) $exchangeId);
        }

        return $query;
    }

    /**
     * Scope query to filter by exchange commodity mapping ID.
     *
     * @param  Builder<ExchangeInstrument>  $query
     * @return Builder<ExchangeInstrument>
     */
    public function scopeMapping(Builder $query, mixed $mappingId): Builder
    {
        if ($mappingId !== null && $mappingId !== '') {
            $query->where('exchange_commodity_mapping_id', (int) $mappingId);
        }

        return $query;
    }

    /**
     * Scope query to filter by canonical internal commodity ID through mapping.
     *
     * @param  Builder<ExchangeInstrument>  $query
     * @return Builder<ExchangeInstrument>
     */
    public function scopeCommodity(Builder $query, mixed $commodityId): Builder
    {
        if ($commodityId !== null && $commodityId !== '') {
            $query->whereHas('mapping', function (Builder $q) use ($commodityId) {
                $q->where('commodity_id', (int) $commodityId);
            });
        }

        return $query;
    }

    /**
     * Scope query to filter by instrument type (future/option).
     *
     * @param  Builder<ExchangeInstrument>  $query
     * @return Builder<ExchangeInstrument>
     */
    public function scopeInstrumentType(Builder $query, mixed $type): Builder
    {
        if ($type !== null && $type !== '') {
            $value = $type instanceof InstrumentType ? $type->value : (string) $type;
            $query->where('instrument_type', $value);
        }

        return $query;
    }

    /**
     * Scope query to filter by lifecycle status.
     *
     * @param  Builder<ExchangeInstrument>  $query
     * @return Builder<ExchangeInstrument>
     */
    public function scopeLifecycleStatus(Builder $query, mixed $status): Builder
    {
        if ($status !== null && $status !== '') {
            $value = $status instanceof InstrumentLifecycleStatus ? $status->value : (string) $status;
            $query->where('lifecycle_status', $value);
        }

        return $query;
    }

    /**
     * Scope query to filter by expiry date range.
     *
     * @param  Builder<ExchangeInstrument>  $query
     * @return Builder<ExchangeInstrument>
     */
    public function scopeExpiryRange(Builder $query, ?string $from, ?string $to): Builder
    {
        if (! empty($from)) {
            $query->whereDate('actual_expiry_date', '>=', $from);
        }

        if (! empty($to)) {
            $query->whereDate('actual_expiry_date', '<=', $to);
        }

        return $query;
    }

    /**
     * Scope query to search by symbol, external instrument ID, or instrument name.
     *
     * @param  Builder<ExchangeInstrument>  $query
     * @return Builder<ExchangeInstrument>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term !== '') {
            $query->where(function (Builder $q) use ($term) {
                $q->where('symbol', 'LIKE', "%{$term}%")
                    ->orWhere('external_instrument_id', 'LIKE', "%{$term}%")
                    ->orWhere('instrument_name', 'LIKE', "%{$term}%");
            });
        }

        return $query;
    }

    /**
     * Scope query to sort by a whitelisted column and order.
     *
     * @param  Builder<ExchangeInstrument>  $query
     * @return Builder<ExchangeInstrument>
     */
    public function scopeSorted(Builder $query, ?string $sortBy = 'actual_expiry_date', ?string $sortOrder = 'asc'): Builder
    {
        $sortBy = strtolower(trim((string) $sortBy));
        $sortOrder = strtolower(trim((string) $sortOrder));

        if (! in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true)) {
            $sortBy = 'actual_expiry_date';
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
