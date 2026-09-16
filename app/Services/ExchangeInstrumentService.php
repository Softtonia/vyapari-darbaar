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
     * Manually create a new exchange instrument.
     *
     * @param  array<string, mixed>  $data
     */
    public function createInstrument(array $data): ExchangeInstrument
    {
        $mappingId = (int) $data['exchange_commodity_mapping_id'];
        $mapping = ExchangeCommodityMapping::findOrFail($mappingId);

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
            return ExchangeInstrument::create([
                'exchange_id' => $exchangeId,
                'exchange_commodity_mapping_id' => $mappingId,
                'external_instrument_id' => $externalInstrumentId,
                'symbol' => trim((string) $data['symbol']),
                'instrument_name' => isset($data['instrument_name']) ? trim((string) $data['instrument_name']) : null,
                'instrument_type' => $instrumentType,
                'original_expiry_date' => $data['original_expiry_date'] ?? null,
                'actual_expiry_date' => $data['actual_expiry_date'],
                'strike_price' => array_key_exists('strike_price', $data) ? $data['strike_price'] : null,
                'option_type' => $optionType,
                'lot_size' => $data['lot_size'],
                'tick_size' => $data['tick_size'],
                'quote_unit' => $data['quote_unit'] ?? null,
                'contract_unit' => $data['contract_unit'] ?? null,
                'lifecycle_status' => $lifecycleStatus,
                'is_enabled' => array_key_exists('is_enabled', $data) ? (bool) $data['is_enabled'] : true,
                'listed_at' => $data['listed_at'] ?? null,
                'delisted_at' => $data['delisted_at'] ?? null,
            ]);
        });

        $this->clearCache($mappingId);

        return $instrument->fresh([
            'exchange:id,name,code',
            'mapping:id,exchange_id,commodity_id,external_symbol,external_name',
            'mapping.commodity:id,name,code,unit',
        ]);
    }

    /**
     * Manually update an existing exchange instrument.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateInstrument(ExchangeInstrument $instrument, array $data): ExchangeInstrument
    {
        $oldMappingId = (int) $instrument->exchange_commodity_mapping_id;
        $newMappingId = array_key_exists('exchange_commodity_mapping_id', $data) && $data['exchange_commodity_mapping_id'] !== null
            ? (int) $data['exchange_commodity_mapping_id']
            : $oldMappingId;

        if ($newMappingId !== $oldMappingId) {
            $mapping = ExchangeCommodityMapping::findOrFail($newMappingId);
            if ((int) $mapping->exchange_id !== (int) $instrument->exchange_id) {
                throw new InvalidArgumentException("Exchange mismatch: mapping {$newMappingId} belongs to exchange {$mapping->exchange_id}, not {$instrument->exchange_id}.");
            }
        }

        $updateData = [];

        if (array_key_exists('exchange_commodity_mapping_id', $data) && $data['exchange_commodity_mapping_id'] !== null) {
            $updateData['exchange_commodity_mapping_id'] = (int) $data['exchange_commodity_mapping_id'];
        }

        if (array_key_exists('external_instrument_id', $data)) {
            $updateData['external_instrument_id'] = $data['external_instrument_id'] !== null && trim((string) $data['external_instrument_id']) !== ''
                ? trim((string) $data['external_instrument_id'])
                : null;
        }

        if (array_key_exists('symbol', $data)) {
            $updateData['symbol'] = trim((string) $data['symbol']);
        }

        if (array_key_exists('instrument_name', $data)) {
            $updateData['instrument_name'] = $data['instrument_name'] !== null ? trim((string) $data['instrument_name']) : null;
        }

        if (array_key_exists('instrument_type', $data)) {
            $updateData['instrument_type'] = $data['instrument_type'] instanceof InstrumentType
                ? $data['instrument_type']->value
                : (string) $data['instrument_type'];
        }

        if (array_key_exists('original_expiry_date', $data)) {
            $updateData['original_expiry_date'] = $data['original_expiry_date'];
        }

        if (array_key_exists('actual_expiry_date', $data)) {
            $updateData['actual_expiry_date'] = $data['actual_expiry_date'];
        }

        if (array_key_exists('strike_price', $data)) {
            $updateData['strike_price'] = $data['strike_price'];
        }

        if (array_key_exists('option_type', $data)) {
            $updateData['option_type'] = ! empty($data['option_type'])
                ? ($data['option_type'] instanceof OptionType ? $data['option_type']->value : (string) $data['option_type'])
                : null;
        }

        if (array_key_exists('lot_size', $data)) {
            $updateData['lot_size'] = $data['lot_size'];
        }

        if (array_key_exists('tick_size', $data)) {
            $updateData['tick_size'] = $data['tick_size'];
        }

        if (array_key_exists('quote_unit', $data)) {
            $updateData['quote_unit'] = $data['quote_unit'];
        }

        if (array_key_exists('contract_unit', $data)) {
            $updateData['contract_unit'] = $data['contract_unit'];
        }

        if (array_key_exists('lifecycle_status', $data)) {
            $updateData['lifecycle_status'] = $data['lifecycle_status'] instanceof InstrumentLifecycleStatus
                ? $data['lifecycle_status']->value
                : (string) $data['lifecycle_status'];
        }

        if (array_key_exists('is_enabled', $data)) {
            $updateData['is_enabled'] = (bool) $data['is_enabled'];
        }

        if (array_key_exists('listed_at', $data)) {
            $updateData['listed_at'] = $data['listed_at'];
        }

        if (array_key_exists('delisted_at', $data)) {
            $updateData['delisted_at'] = $data['delisted_at'];
        }

        $instrument = DB::transaction(function () use ($instrument, $updateData) {
            $instrument->update($updateData);

            return $instrument->fresh([
                'exchange:id,name,code',
                'mapping:id,exchange_id,commodity_id,external_symbol,external_name',
                'mapping.commodity:id,name,code,unit',
            ]);
        });

        $this->clearCache($oldMappingId, [$newMappingId]);

        return $instrument;
    }

    /**
     * Delete an exchange instrument if no historical market bhavcopies exist.
     */
    public function deleteInstrument(ExchangeInstrument $instrument): bool
    {
        if ($instrument->bhavcopies()->exists()) {
            throw new \DomainException('Cannot delete instrument because historical market data (bhavcopies) exists. You can set lifecycle_status to delisted or is_enabled to false instead.');
        }

        $mappingId = (int) $instrument->exchange_commodity_mapping_id;

        DB::transaction(function () use ($instrument) {
            $instrument->delete();
        });

        $this->clearCache($mappingId);

        return true;
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
