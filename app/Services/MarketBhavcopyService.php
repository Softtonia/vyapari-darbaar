<?php

namespace App\Services;

use App\DTOs\Market\NormalizedBhavcopyRowDTO;
use App\Models\ExchangeInstrument;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarketBhavcopyService
{
    /**
     * Default chunk size for database bulk operations.
     */
    public const DEFAULT_CHUNK_SIZE = 200;

    /**
     * Maximum sample errors to retain in memory / return summary.
     */
    public const MAX_SAMPLE_ERRORS = 50;

    /**
     * Ingest and bulk-upsert a collection/iterable of normalized Bhavcopy rows.
     *
     * @param int $exchangeId
     * @param iterable<NormalizedBhavcopyRowDTO|array<string, mixed>> $rows
     * @param int $chunkSize
     * @param string|null $receivedAt
     * @return array{
     *     records_received: int,
     *     records_inserted: int,
     *     records_updated: int,
     *     records_skipped: int,
     *     records_failed: int,
     *     sample_errors: list<array{row_index: int, error: string, data?: mixed}>,
     *     unmapped_count: int,
     *     validation_error_count: int
     * }
     */
    public function upsertNormalizedRows(
        int $exchangeId,
        iterable $rows,
        int $chunkSize = self::DEFAULT_CHUNK_SIZE,
        ?string $receivedAt = null
    ): array {
        $receivedAtTimestamp = $receivedAt ?? Carbon::now()->toDateTimeString();
        $now = Carbon::now()->toDateTimeString();

        $recordsReceived = 0;
        $recordsInserted = 0;
        $recordsUpdated = 0;
        $recordsSkipped = 0;
        $recordsFailed = 0;

        $unmappedCount = 0;
        $validationErrorCount = 0;
        $sampleErrors = [];

        // Build active external instrument map for this exchange to avoid per-row queries
        $instrumentMap = ExchangeInstrument::query()
            ->where('exchange_id', $exchangeId)
            ->pluck('id', 'external_instrument_id')
            ->all();

        // Valid instrument IDs belonging to this exchange (for pre-resolved instrument_id checks)
        $validInstrumentIds = ExchangeInstrument::query()
            ->where('exchange_id', $exchangeId)
            ->pluck('id')
            ->flip()
            ->all();

        $validRowsToUpsert = [];
        $instrumentDatePairs = [];

        $rowIndex = 0;
        foreach ($rows as $rawRow) {
            $rowIndex++;
            $recordsReceived++;

            $dto = $rawRow instanceof NormalizedBhavcopyRowDTO
                ? $rawRow
                : NormalizedBhavcopyRowDTO::fromArray((array) $rawRow);

            // Validation: trade_date must be valid
            if (empty($dto->tradeDate) || ! $this->isValidDate($dto->tradeDate)) {
                $recordsFailed++;
                $validationErrorCount++;
                $this->addSampleError($sampleErrors, $rowIndex, "Invalid or missing trade_date: '{$dto->tradeDate}'", [
                    'external_instrument_id' => $dto->externalInstrumentId,
                    'trade_date' => $dto->tradeDate,
                ]);
                continue;
            }

            // Resolve internal instrument ID
            $resolvedInstrumentId = null;
            if ($dto->instrumentId !== null && isset($validInstrumentIds[$dto->instrumentId])) {
                $resolvedInstrumentId = $dto->instrumentId;
            } elseif ($dto->externalInstrumentId !== null && isset($instrumentMap[$dto->externalInstrumentId])) {
                $resolvedInstrumentId = $instrumentMap[$dto->externalInstrumentId];
            }

            if ($resolvedInstrumentId === null) {
                $recordsSkipped++;
                $unmappedCount++;
                $this->addSampleError($sampleErrors, $rowIndex, "Unmapped exchange instrument: external ID '{$dto->externalInstrumentId}' not found for exchange ID {$exchangeId}", [
                    'external_instrument_id' => $dto->externalInstrumentId,
                    'trade_date' => $dto->tradeDate,
                ]);
                continue;
            }

            $dbRow = $dto->toDatabaseRow($resolvedInstrumentId, $receivedAtTimestamp);
            $dbRow['created_at'] = $now;
            $dbRow['updated_at'] = $now;

            $validRowsToUpsert[] = $dbRow;
            $instrumentDatePairs[] = [
                'instrument_id' => $resolvedInstrumentId,
                'trade_date' => $dto->tradeDate,
            ];
        }

        // Process valid rows in bounded chunks
        if (! empty($validRowsToUpsert)) {
            $chunks = array_chunk($validRowsToUpsert, max(1, $chunkSize));
            $pairChunks = array_chunk($instrumentDatePairs, max(1, $chunkSize));

            foreach ($chunks as $chunkIndex => $chunk) {
                $pairChunk = $pairChunks[$chunkIndex];

                // Determine existing vs new for accurate counter statistics
                $existingCount = $this->countExistingPairs($pairChunk);
                $chunkInserted = count($chunk) - $existingCount;
                $chunkUpdated = $existingCount;

                DB::transaction(function () use ($chunk) {
                    DB::table('market_bhavcopies')->upsert(
                        $chunk,
                        ['exchange_instrument_id', 'trade_date'],
                        [
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
                            'updated_at',
                        ]
                    );
                });

                $recordsInserted += $chunkInserted;
                $recordsUpdated += $chunkUpdated;
            }
        }

        return [
            'records_received' => $recordsReceived,
            'records_inserted' => $recordsInserted,
            'records_updated' => $recordsUpdated,
            'records_skipped' => $recordsSkipped,
            'records_failed' => $recordsFailed,
            'sample_errors' => $sampleErrors,
            'unmapped_count' => $unmappedCount,
            'validation_error_count' => $validationErrorCount,
        ];
    }

    /**
     * Count existing bhavcopy records for pairs of (exchange_instrument_id, trade_date).
     *
     * @param list<array{instrument_id: int, trade_date: string}> $pairs
     */
    protected function countExistingPairs(array $pairs): int
    {
        if (empty($pairs)) {
            return 0;
        }

        $query = DB::table('market_bhavcopies');

        $query->where(function ($q) use ($pairs) {
            foreach ($pairs as $pair) {
                $q->orWhere(function ($sub) use ($pair) {
                    $sub->where('exchange_instrument_id', $pair['instrument_id'])
                        ->where('trade_date', $pair['trade_date']);
                });
            }
        });

        return $query->count();
    }

    /**
     * Validate date string in Y-m-d format.
     */
    protected function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    /**
     * Safely add a sample error up to MAX_SAMPLE_ERRORS.
     *
     * @param list<array<string, mixed>> $errorsList
     * @param int $rowIndex
     * @param string $message
     * @param mixed $data
     */
    protected function addSampleError(array &$errorsList, int $rowIndex, string $message, mixed $data = null): void
    {
        if (count($errorsList) < self::MAX_SAMPLE_ERRORS) {
            $errorsList[] = [
                'row_index' => $rowIndex,
                'error' => $message,
                'data' => $data,
            ];
        }
    }
}
