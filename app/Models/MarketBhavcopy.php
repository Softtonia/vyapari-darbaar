<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketBhavcopy extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'market_bhavcopies';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'exchange_instrument_id',
        'trade_date',
        'open_price',
        'high_price',
        'low_price',
        'close_price',
        'last_price',
        'previous_close_price',
        'settlement_price',
        'volume',
        'traded_value',
        'number_of_trades',
        'open_interest',
        'change_in_open_interest',
        'source_timestamp',
        'received_at',
    ];

    /**
     * Allowed columns for dynamic sorting.
     *
     * @var list<string>
     */
    public const ALLOWED_SORT_COLUMNS = [
        'id',
        'exchange_instrument_id',
        'trade_date',
        'open_price',
        'high_price',
        'low_price',
        'close_price',
        'last_price',
        'settlement_price',
        'volume',
        'traded_value',
        'open_interest',
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
            'exchange_instrument_id' => 'integer',
            'trade_date' => 'date:Y-m-d',
            'open_price' => 'decimal:8',
            'high_price' => 'decimal:8',
            'low_price' => 'decimal:8',
            'close_price' => 'decimal:8',
            'last_price' => 'decimal:8',
            'previous_close_price' => 'decimal:8',
            'settlement_price' => 'decimal:8',
            'volume' => 'decimal:6',
            'traded_value' => 'decimal:6',
            'number_of_trades' => 'integer',
            'open_interest' => 'decimal:6',
            'change_in_open_interest' => 'decimal:6',
            'source_timestamp' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    /**
     * Get the parent exchange instrument.
     *
     * @return BelongsTo<ExchangeInstrument, $this>
     */
    public function instrument(): BelongsTo
    {
        return $this->belongsTo(ExchangeInstrument::class, 'exchange_instrument_id');
    }

    /**
     * Scope a query to a specific instrument.
     *
     * @param Builder<self> $query
     */
    public function scopeForInstrument(Builder $query, int $instrumentId): Builder
    {
        return $query->where('exchange_instrument_id', $instrumentId);
    }

    /**
     * Scope a query to a date range (inclusive).
     *
     * @param Builder<self> $query
     */
    public function scopeDateBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('trade_date', [$from, $to]);
    }

    /**
     * Scope a query to a specific trade date.
     *
     * @param Builder<self> $query
     */
    public function scopeForTradeDate(Builder $query, string $tradeDate): Builder
    {
        return $query->where('trade_date', $tradeDate);
    }
}
