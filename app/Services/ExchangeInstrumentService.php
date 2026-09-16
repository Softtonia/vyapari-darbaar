<?php

namespace App\Services;

use App\Enums\InstrumentLifecycleStatus;
use App\Enums\InstrumentType;
use App\Enums\OptionType;
use App\Models\ExchangeCommodityMapping;
use App\Models\ExchangeInstrument;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExchangeInstrumentService
{
    /**
     * Cache key prefix for mapping-scoped instrument options.
     */
    public const CACHE_KEY_OPTIONS_MAPPING_PREFIX = 'exchange-instruments:options:mapping:';

    /**
     * Cache TTL in seconds (1 hour).
     */
    public const CACHE_TTL_SECONDS = 3600;

    /**
     * Get a paginated list of instruments with dynamic filtering, sorting and eager loading.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listInstruments(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = max(1, min(100, $perPage));

        $sortBy = (string) ($filters['sort_by'] ?? 'actual_expiry_date');
        $sortOrder = (string) ($filters['sort_order'] ?? 'asc');

        $query = ExchangeInstrument::query()
            ->with([
                'exchange:id,name,code',
                'mapping:id,exchange_id,commodity_id,external_symbol,external_name',
                'mapping.commodity:id,name,code,unit',
            ])
            ->select([
                'id',
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
                'created_at',
                'updated_at',
            ])
            ->search($filters['search'] ?? null)
            ->exchange($filters['exchange_id'] ?? null)
            ->mapping($filters['exchange_commodity_mapping_id'] ?? null)
            ->commodity($filters['commodity_id'] ?? null)
            ->instrumentType($filters['instrument_type'] ?? null)
            ->lifecycleStatus($filters['lifecycle_status'] ?? null)
            ->expiryRange($filters['expiry_from'] ?? null, $filters['expiry_to'] ?? null)
            ->when(isset($filters['is_enabled']), fn ($q) => $q->where('is_enabled', filter_var($filters['is_enabled'], FILTER_VALIDATE_BOOLEAN)))
            ->sorted($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get lightweight, cached list of active/enabled instruments for a mapping.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOptions(?int $mappingId = null): array
    {
        if ($mappingId === null) {
            return [];
        }

        $cacheKey = self::CACHE_KEY_OPTIONS_MAPPING_PREFIX.$mappingId;

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($mappingId) {
            return ExchangeInstrument::query()
                ->enabled()
                ->activeLifecycle()
                ->where('exchange_commodity_mapping_id', $mappingId)
                ->select([
                    'id',
                    'exchange_id',
                    'exchange_commodity_mapping_id',
                    'external_instrument_id',
                    'symbol',
                    'instrument_name',
                    'instrument_type',
                    'actual_expiry_date',
                ])
                ->orderBy('actual_expiry_date', 'asc')
                ->get()
                ->toArray();
        });
    }

    /**
     * Set local is_enabled flag for an instrument.
     */
    public function setEnabled(ExchangeInstrument $instrument, bool $isEnabled): ExchangeInstrument
    {
        $updatedInstrument = DB::transaction(function () use ($instrument, $isEnabled) {
            $instrument->update([
                'is_enabled' => $isEnabled,
            ]);

            return $instrument->fresh([
                'exchange:id,name,code',
                'mapping:id,exchange_id,commodity_id,external_symbol,external_name',
                'mapping.commodity:id,name,code,unit',
            ]);
        });

        $this->clearCache((int) $updatedInstrument->exchange_commodity_mapping_id);

        return $updatedInstrument;
    }

    /**
     * Idempotent internal upsert from exchange reference-data feed.
     * (Called by future reference-data sync jobs / providers).
     *
     * @param  array<string, mixed>  $data
     */
    public function upsertFromSource(array $data): ExchangeInstrument
    {
        $mappingId = (int) $data['exchange_commodity_mapping_id'];
        $mapping = ExchangeCommodityMapping::find($mappingId);

        if (! $mapping) {
            throw new InvalidArgumentException("Exchange commodity mapping ID {$mappingId} not found.");
        }

        $exchangeId = (int) ($data['exchange_id'] ?? $mapping->exchange_id);
        if ($exchangeId !== (int) $mapping->exchange_id) {
            throw new InvalidArgumentException("Exchange mismatch: mapping {$mappingId} belongs to exchange {$mapping->exchange_id}, not {$exchangeId}.");
        }

        $instrumentType = $data['instrument_type'] instanceof InstrumentType
            ? $data['instrument_type']->value
            : (string) $data['instrument_type'];

        $optionType = null;
        if (! empty($data['option_type'])) {
            $optionType = $data['option_type'] instanceof OptionType
                ? $data['option_type']->value
                : (string) $data['option_type'];
        }

        $lifecycleStatus = ! empty($data['lifecycle_status'])
            ? ($data['lifecycle_status'] instanceof InstrumentLifecycleStatus ? $data['lifecycle_status']->value : (string) $data['lifecycle_status'])
            : InstrumentLifecycleStatus::ACTIVE->value;

        $externalInstrumentId = isset($data['external_instrument_id']) && trim((string) $data['external_instrument_id']) !== ''
            ? trim((string) $data['external_instrument_id'])
            : null;

        $instrument = DB::transaction(function () use (
            $exchangeId,
            $mappingId,
            $externalInstrumentId,
            $data,
            $instrumentType,
            $optionType,
            $lifecycleStatus
        ) {
            $attributes = [
                'exchange_id' => $exchangeId,
                'exchange_commodity_mapping_id' => $mappingId,
                'symbol' => trim((string) $data['symbol']),
                'instrument_name' => isset($data['instrument_name']) ? trim((string) $data['instrument_name']) : null,
                'instrument_type' => $instrumentType,
                'original_expiry_date' => $data['original_expiry_date'] ?? null,
                'actual_expiry_date' => $data['actual_expiry_date'],
                'strike_price' => array_key_exists('strike_price', $data) ? $data['strike_price'] : null,
                'option_type' => $optionType,
                'lot_size' => array_key_exists('lot_size', $data) ? $data['lot_size'] : null,
                'tick_size' => array_key_exists('tick_size', $data) ? $data['tick_size'] : null,
                'quote_unit' => $data['quote_unit'] ?? null,
                'contract_unit' => $data['contract_unit'] ?? null,
                'lifecycle_status' => $lifecycleStatus,
                'is_enabled' => array_key_exists('is_enabled', $data) ? (bool) $data['is_enabled'] : true,
                'listed_at' => $data['listed_at'] ?? null,
                'delisted_at' => $data['delisted_at'] ?? null,
            ];

            if ($externalInstrumentId !== null) {
                return ExchangeInstrument::updateOrCreate(
                    [
                        'exchange_id' => $exchangeId,
                        'external_instrument_id' => $externalInstrumentId,
                    ],
                    $attributes
                );
            }

            return ExchangeInstrument::updateOrCreate(
                [
                    'exchange_id' => $exchangeId,
                    'exchange_commodity_mapping_id' => $mappingId,
                    'symbol' => $attributes['symbol'],
                    'actual_expiry_date' => $attributes['actual_expiry_date'],
                ],
                $attributes
            );
        });

        $this->clearCache($mappingId);

        return $instrument->fresh([
            'exchange:id,name,code',
            'mapping:id,exchange_id,commodity_id,external_symbol,external_name',
            'mapping.commodity:id,name,code,unit',
        ]);
    }

    /**
     * Invalidate instrument options cache.
     *
     * @param  int|null  $mappingId Single mapping ID
     * @param  list<int>  $mappingIds List of mapping IDs
     */
    public function clearCache(?int $mappingId = null, array $mappingIds = []): void
    {
        try {
            if ($mappingId !== null) {
                Cache::forget(self::CACHE_KEY_OPTIONS_MAPPING_PREFIX.$mappingId);
            }

            foreach ($mappingIds as $mId) {
                if ($mId) {
                    Cache::forget(self::CACHE_KEY_OPTIONS_MAPPING_PREFIX.$mId);
                }
            }
        } catch (\Throwable $e) {
            // Non-blocking cache exception
        }
    }
}
