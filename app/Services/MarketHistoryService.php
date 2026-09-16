<?php

namespace App\Services;

use App\Models\ExchangeInstrument;
use App\Models\MarketBhavcopy;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class MarketHistoryService
{
    /**
     * Default number of items returned if unspecified.
     */
    public const DEFAULT_LIMIT = 100;

    /**
     * Maximum allowed history records in a single query.
     */
    public const MAX_LIMIT = 500;

    /**
     * Retrieve historical EOD market bhavcopy candles for a specific instrument.
     *
     * @param ExchangeInstrument $instrument
     * @param array<string, mixed> $filters
     * @return array{
     *     instrument: ExchangeInstrument,
     *     interval: string,
     *     from: string,
     *     to: string,
     *     items: Collection<int, MarketBhavcopy>
     * }
     *
     * @throws ValidationException
     */
    public function getInstrumentHistory(ExchangeInstrument $instrument, array $filters = []): array
    {
        $interval = strtoupper((string) ($filters['interval'] ?? '1D'));

        if ($interval !== '1D') {
            throw ValidationException::withMessages([
                'interval' => ["The selected interval '{$interval}' is unsupported. Only '1D' (daily EOD) is supported."],
            ]);
        }

        $toDate = ! empty($filters['to'])
            ? Carbon::parse($filters['to'])->format('Y-m-d')
            : Carbon::today()->format('Y-m-d');

        $fromDate = ! empty($filters['from'])
            ? Carbon::parse($filters['from'])->format('Y-m-d')
            : Carbon::parse($toDate)->subDays(30)->format('Y-m-d');

        if ($fromDate > $toDate) {
            throw ValidationException::withMessages([
                'from' => ['The from date cannot be later than the to date.'],
            ]);
        }

        $limit = isset($filters['limit'])
            ? min((int) $filters['limit'], self::MAX_LIMIT)
            : self::DEFAULT_LIMIT;

        if ($limit <= 0) {
            $limit = self::DEFAULT_LIMIT;
        }

        // Prevent N+1 on relation lookups
        $instrument->loadMissing([
            'exchange:id,name,code,slug',
            'mapping.commodity:id,name,code,slug,canonical_name',
        ]);

        $items = MarketBhavcopy::query()
            ->forInstrument($instrument->id)
            ->dateBetween($fromDate, $toDate)
            ->orderBy('trade_date', 'asc')
            ->limit($limit)
            ->get();

        return [
            'instrument' => $instrument,
            'interval' => '1D',
            'from' => $fromDate,
            'to' => $toDate,
            'items' => $items,
        ];
    }
}
