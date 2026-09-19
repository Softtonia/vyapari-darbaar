<?php

namespace App\DTOs\Market;

readonly class NormalizedBhavcopyRowDTO
{
    /**
     * @param  string|null  $externalInstrumentId  External source identifier/token
     * @param  int|null  $instrumentId  Pre-resolved internal exchange_instrument_id
     * @param  string  $tradeDate  Format: YYYY-MM-DD
     * @param  string|null  $openPrice  Decimal string or null
     * @param  string|null  $highPrice  Decimal string or null
     * @param  string|null  $lowPrice  Decimal string or null
     * @param  string|null  $closePrice  Decimal string or null
     * @param  string|null  $lastPrice  Decimal string or null
     * @param  string|null  $previousClosePrice  Decimal string or null
     * @param  string|null  $settlementPrice  Decimal string or null
     * @param  string|null  $volume  Decimal string or null
     * @param  string|null  $tradedValue  Decimal string or null
     * @param  int|null  $numberOfTrades  Integer count or null
     * @param  string|null  $openInterest  Decimal string or null
     * @param  string|null  $changeInOpenInterest  Decimal string or null
     * @param  string|null  $sourceTimestamp  Source datetime string or null
     */
    public function __construct(
        public ?string $externalInstrumentId,
        public string $tradeDate,
        public ?int $instrumentId = null,
        public ?string $openPrice = null,
        public ?string $highPrice = null,
        public ?string $lowPrice = null,
        public ?string $closePrice = null,
        public ?string $lastPrice = null,
        public ?string $previousClosePrice = null,
        public ?string $settlementPrice = null,
        public ?string $volume = null,
        public ?string $tradedValue = null,
        public ?int $numberOfTrades = null,
        public ?string $openInterest = null,
        public ?string $changeInOpenInterest = null,
        public ?string $sourceTimestamp = null,
    ) {}

    /**
     * Create a DTO instance from an associative array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            externalInstrumentId: isset($data['external_instrument_id']) ? (string) $data['external_instrument_id'] : (isset($data['externalInstrumentId']) ? (string) $data['externalInstrumentId'] : null),
            tradeDate: (string) ($data['trade_date'] ?? $data['tradeDate'] ?? ''),
            instrumentId: isset($data['instrument_id']) ? (int) $data['instrument_id'] : (isset($data['instrumentId']) ? (int) $data['instrumentId'] : null),
            openPrice: isset($data['open_price']) && $data['open_price'] !== '' ? (string) $data['open_price'] : (isset($data['openPrice']) && $data['openPrice'] !== '' ? (string) $data['openPrice'] : null),
            highPrice: isset($data['high_price']) && $data['high_price'] !== '' ? (string) $data['high_price'] : (isset($data['highPrice']) && $data['highPrice'] !== '' ? (string) $data['highPrice'] : null),
            lowPrice: isset($data['low_price']) && $data['low_price'] !== '' ? (string) $data['low_price'] : (isset($data['lowPrice']) && $data['lowPrice'] !== '' ? (string) $data['lowPrice'] : null),
            closePrice: isset($data['close_price']) && $data['close_price'] !== '' ? (string) $data['close_price'] : (isset($data['closePrice']) && $data['closePrice'] !== '' ? (string) $data['closePrice'] : null),
            lastPrice: isset($data['last_price']) && $data['last_price'] !== '' ? (string) $data['last_price'] : (isset($data['lastPrice']) && $data['lastPrice'] !== '' ? (string) $data['lastPrice'] : null),
            previousClosePrice: isset($data['previous_close_price']) && $data['previous_close_price'] !== '' ? (string) $data['previous_close_price'] : (isset($data['previousClosePrice']) && $data['previousClosePrice'] !== '' ? (string) $data['previousClosePrice'] : null),
            settlementPrice: isset($data['settlement_price']) && $data['settlement_price'] !== '' ? (string) $data['settlement_price'] : (isset($data['settlementPrice']) && $data['settlementPrice'] !== '' ? (string) $data['settlementPrice'] : null),
            volume: isset($data['volume']) && $data['volume'] !== '' ? (string) $data['volume'] : null,
            tradedValue: isset($data['traded_value']) && $data['traded_value'] !== '' ? (string) $data['traded_value'] : (isset($data['tradedValue']) && $data['tradedValue'] !== '' ? (string) $data['tradedValue'] : null),
            numberOfTrades: isset($data['number_of_trades']) && $data['number_of_trades'] !== null ? (int) $data['number_of_trades'] : (isset($data['numberOfTrades']) && $data['numberOfTrades'] !== null ? (int) $data['numberOfTrades'] : null),
            openInterest: isset($data['open_interest']) && $data['open_interest'] !== '' ? (string) $data['open_interest'] : (isset($data['openInterest']) && $data['openInterest'] !== '' ? (string) $data['openInterest'] : null),
            changeInOpenInterest: isset($data['change_in_open_interest']) && $data['change_in_open_interest'] !== '' ? (string) $data['change_in_open_interest'] : (isset($data['changeInOpenInterest']) && $data['changeInOpenInterest'] !== '' ? (string) $data['changeInOpenInterest'] : null),
            sourceTimestamp: isset($data['source_timestamp']) ? (string) $data['source_timestamp'] : (isset($data['sourceTimestamp']) ? (string) $data['sourceTimestamp'] : null),
        );
    }

    /**
     * Convert DTO to array for database upsert.
     *
     * @return array<string, mixed>
     */
    public function toDatabaseRow(int $resolvedInstrumentId, string $receivedAt): array
    {
        return [
            'exchange_instrument_id' => $resolvedInstrumentId,
            'trade_date' => $this->tradeDate,
            'open_price' => $this->openPrice,
            'high_price' => $this->highPrice,
            'low_price' => $this->lowPrice,
            'close_price' => $this->closePrice,
            'last_price' => $this->lastPrice,
            'previous_close_price' => $this->previousClosePrice,
            'settlement_price' => $this->settlementPrice,
            'volume' => $this->volume,
            'traded_value' => $this->tradedValue,
            'number_of_trades' => $this->numberOfTrades,
            'open_interest' => $this->openInterest,
            'change_in_open_interest' => $this->changeInOpenInterest,
            'source_timestamp' => $this->sourceTimestamp,
            'received_at' => $receivedAt,
        ];
    }
}
