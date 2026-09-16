<?php

namespace App\Services;

use App\Enums\IngestionSourceType;
use App\Enums\IngestionStatus;
use App\Models\MarketIngestionRun;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MarketIngestionRunService
{
    /**
     * Create a new pending market ingestion run.
     *
     * @param int $exchangeId
     * @param IngestionSourceType|string $sourceType
     * @param string|null $tradeDate Format: YYYY-MM-DD
     * @param string|null $fileName
     * @param string|null $checksum SHA-256 string
     * @param string|null $storagePath
     * @return MarketIngestionRun
     */
    public function createRun(
        int $exchangeId,
        IngestionSourceType|string $sourceType,
        ?string $tradeDate = null,
        ?string $fileName = null,
        ?string $checksum = null,
        ?string $storagePath = null
    ): MarketIngestionRun {
        $type = $sourceType instanceof IngestionSourceType ? $sourceType->value : $sourceType;

        return MarketIngestionRun::create([
            'exchange_id' => $exchangeId,
            'source_type' => $type,
            'trade_date' => $tradeDate,
            'source_file_name' => $fileName,
            'source_checksum' => $checksum,
            'storage_path' => $storagePath,
            'status' => IngestionStatus::PENDING->value,
            'records_received' => 0,
            'records_inserted' => 0,
            'records_updated' => 0,
            'records_skipped' => 0,
            'records_failed' => 0,
            'started_at' => null,
            'finished_at' => null,
            'error_summary' => null,
        ]);
    }

    /**
     * Check if an identical completed ingestion run already exists by checksum.
     */
    public function findCompletedRunByChecksum(
        int $exchangeId,
        IngestionSourceType|string $sourceType,
        string $checksum
    ): ?MarketIngestionRun {
        $type = $sourceType instanceof IngestionSourceType ? $sourceType->value : $sourceType;

        return MarketIngestionRun::query()
            ->where('exchange_id', $exchangeId)
            ->where('source_type', $type)
            ->where('source_checksum', $checksum)
            ->where('status', IngestionStatus::COMPLETED->value)
            ->latest('id')
            ->first();
    }

    /**
     * Mark an ingestion run as processing.
     */
    public function markProcessing(MarketIngestionRun $run): void
    {
        $run->update([
            'status' => IngestionStatus::PROCESSING->value,
            'started_at' => Carbon::now(),
        ]);
    }

    /**
     * Mark an ingestion run as successfully completed.
     *
     * @param MarketIngestionRun $run
     * @param array<string, mixed> $stats
     */
    public function markCompleted(MarketIngestionRun $run, array $stats): void
    {
        $errorSummary = $this->buildBoundedErrorSummary($stats);

        $run->update([
            'status' => IngestionStatus::COMPLETED->value,
            'finished_at' => Carbon::now(),
            'records_received' => (int) ($stats['records_received'] ?? 0),
            'records_inserted' => (int) ($stats['records_inserted'] ?? 0),
            'records_updated' => (int) ($stats['records_updated'] ?? 0),
            'records_skipped' => (int) ($stats['records_skipped'] ?? 0),
            'records_failed' => (int) ($stats['records_failed'] ?? 0),
            'error_summary' => empty($errorSummary['sample_errors']) && ($errorSummary['total_errors'] ?? 0) === 0 ? null : $errorSummary,
        ]);
    }

    /**
     * Mark an ingestion run as partial (completed with some skipped/failed rows).
     *
     * @param MarketIngestionRun $run
     * @param array<string, mixed> $stats
     */
    public function markPartial(MarketIngestionRun $run, array $stats): void
    {
        $errorSummary = $this->buildBoundedErrorSummary($stats);

        $run->update([
            'status' => IngestionStatus::PARTIAL->value,
            'finished_at' => Carbon::now(),
            'records_received' => (int) ($stats['records_received'] ?? 0),
            'records_inserted' => (int) ($stats['records_inserted'] ?? 0),
            'records_updated' => (int) ($stats['records_updated'] ?? 0),
            'records_skipped' => (int) ($stats['records_skipped'] ?? 0),
            'records_failed' => (int) ($stats['records_failed'] ?? 0),
            'error_summary' => $errorSummary,
        ]);
    }

    /**
     * Mark an ingestion run as failed due to a fatal exception.
     *
     * @param MarketIngestionRun $run
     * @param string $errorMessage
     * @param array<string, mixed>|null $stats
     */
    public function markFailed(MarketIngestionRun $run, string $errorMessage, ?array $stats = null): void
    {
        $errorSummary = $this->buildBoundedErrorSummary($stats ?? [], $errorMessage);

        $run->update([
            'status' => IngestionStatus::FAILED->value,
            'finished_at' => Carbon::now(),
            'records_received' => (int) ($stats['records_received'] ?? $run->records_received),
            'records_inserted' => (int) ($stats['records_inserted'] ?? $run->records_inserted),
            'records_updated' => (int) ($stats['records_updated'] ?? $run->records_updated),
            'records_skipped' => (int) ($stats['records_skipped'] ?? $run->records_skipped),
            'records_failed' => (int) ($stats['records_failed'] ?? $run->records_failed),
            'error_summary' => $errorSummary,
        ]);
    }

    /**
     * Build bounded and structured error summary.
     *
     * @param array<string, mixed> $stats
     * @param string|null $fatalError
     * @return array<string, mixed>
     */
    public function buildBoundedErrorSummary(array $stats, ?string $fatalError = null): array
    {
        $unmappedCount = (int) ($stats['unmapped_count'] ?? $stats['records_skipped'] ?? 0);
        $validationCount = (int) ($stats['validation_error_count'] ?? $stats['records_failed'] ?? 0);
        $totalErrors = $unmappedCount + $validationCount + ($fatalError !== null ? 1 : 0);

        $sampleErrors = $stats['sample_errors'] ?? [];
        if (count($sampleErrors) > MarketBhavcopyService::MAX_SAMPLE_ERRORS) {
            $sampleErrors = array_slice($sampleErrors, 0, MarketBhavcopyService::MAX_SAMPLE_ERRORS);
        }

        $summary = [
            'total_errors' => $totalErrors,
            'unmapped_count' => $unmappedCount,
            'validation_error_count' => $validationCount,
            'sample_errors' => $sampleErrors,
        ];

        if ($fatalError !== null) {
            $summary['fatal_error'] = $fatalError;
        }

        return $summary;
    }

    /**
     * Retrieve paginated list of ingestion runs with filters.
     *
     * @param array<string, mixed> $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedRuns(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = MarketIngestionRun::query()
            ->with(['exchange:id,name,code,slug']);

        if (! empty($filters['exchange_id'])) {
            $query->where('exchange_id', (int) $filters['exchange_id']);
        }

        if (! empty($filters['source_type'])) {
            $query->where('source_type', $filters['source_type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['trade_date'])) {
            $query->where('trade_date', $filters['trade_date']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        $sortBy = in_array($filters['sort_by'] ?? null, MarketIngestionRun::ALLOWED_SORT_COLUMNS, true)
            ? $filters['sort_by']
            : 'id';

        $sortOrder = strtolower($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage);
    }

    /**
     * Find single ingestion run with relations.
     */
    public function getRunDetails(int $id): MarketIngestionRun
    {
        return MarketIngestionRun::query()
            ->with(['exchange'])
            ->findOrFail($id);
    }
}
