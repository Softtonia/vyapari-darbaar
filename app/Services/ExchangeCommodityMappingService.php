<?php

namespace App\Services;

use App\Models\ExchangeCommodityMapping;
use App\Models\ExchangeInstrument;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ExchangeCommodityMappingService
{
    /**
     * Cache key prefixes.
     */
    public const CACHE_KEY_OPTIONS_EXCHANGE_PREFIX = 'exchange-mappings:options:exchange:';

    public const CACHE_KEY_OPTIONS_COMMODITY_PREFIX = 'exchange-mappings:options:commodity:';

    public const CACHE_KEY_OPTIONS_ALL = 'exchange-mappings:options:all';

    /**
     * Cache TTL in seconds (1 hour).
     */
    public const CACHE_TTL_SECONDS = 3600;

    /**
     * Get a paginated list of commodity mappings with dynamic filtering and sorting.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listMappings(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = max(1, min(100, $perPage));

        $sortBy = (string) ($filters['sort_by'] ?? 'id');
        $sortOrder = (string) ($filters['sort_order'] ?? 'desc');

        $query = ExchangeCommodityMapping::query()
            ->with([
                'exchange:id,name,code',
                'commodity:id,name,code,unit',
            ])
            ->select([
                'id',
                'exchange_id',
                'commodity_id',
                'external_symbol',
                'external_code',
                'external_name',
                'status',
                'created_at',
                'updated_at',
            ])
            ->search($filters['search'] ?? null)
            ->exchange($filters['exchange_id'] ?? null)
            ->commodity($filters['commodity_id'] ?? null)
            ->status($filters['status'] ?? null)
            ->sorted($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get lightweight, cached list of active mappings for dropdown options.
     * Only returns mappings where parent exchange and commodity are active.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOptions(?int $exchangeId = null, ?int $commodityId = null): array
    {
        $cacheKey = match (true) {
            $exchangeId !== null => self::CACHE_KEY_OPTIONS_EXCHANGE_PREFIX.$exchangeId,
            $commodityId !== null => self::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$commodityId,
            default => self::CACHE_KEY_OPTIONS_ALL,
        };

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($exchangeId, $commodityId) {
            return ExchangeCommodityMapping::query()
                ->active()
                ->whereHas('exchange', fn ($q) => $q->active())
                ->whereHas('commodity', fn ($q) => $q->active())
                ->when($exchangeId !== null, fn ($q) => $q->where('exchange_id', $exchangeId))
                ->when($commodityId !== null, fn ($q) => $q->where('commodity_id', $commodityId))
                ->select(['id', 'exchange_id', 'commodity_id', 'external_symbol', 'external_code', 'external_name'])
                ->orderBy('external_symbol', 'asc')
                ->get()
                ->toArray();
        });
    }

    /**
     * Create a new exchange commodity mapping.
     *
     * @param  array<string, mixed>  $data
     */
    public function createMapping(array $data, ?int $adminId = null): ExchangeCommodityMapping
    {
        $mapping = DB::transaction(function () use ($data, $adminId) {
            return ExchangeCommodityMapping::create([
                'exchange_id' => (int) $data['exchange_id'],
                'commodity_id' => (int) $data['commodity_id'],
                'external_symbol' => trim((string) $data['external_symbol']),
                'external_code' => isset($data['external_code']) ? trim((string) $data['external_code']) : null,
                'external_name' => isset($data['external_name']) ? trim((string) $data['external_name']) : null,
                'status' => $data['status'] ?? true,
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]);
        });

        $this->clearCache((int) $mapping->exchange_id, [], (int) $mapping->commodity_id, []);

        return $mapping->load(['exchange:id,name,code,slug', 'commodity:id,name,code,slug,unit']);
    }

    /**
     * Update an existing exchange commodity mapping.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateMapping(ExchangeCommodityMapping $mapping, array $data, ?int $adminId = null): ExchangeCommodityMapping
    {
        $oldExchangeId = (int) $mapping->exchange_id;
        $oldCommodityId = (int) $mapping->commodity_id;

        $updatedMapping = DB::transaction(function () use ($mapping, $data, $adminId) {
            $updateData = [];

            if (array_key_exists('exchange_id', $data)) {
                $updateData['exchange_id'] = (int) $data['exchange_id'];
            }

            if (array_key_exists('commodity_id', $data)) {
                $updateData['commodity_id'] = (int) $data['commodity_id'];
            }

            if (array_key_exists('external_symbol', $data)) {
                $updateData['external_symbol'] = trim((string) $data['external_symbol']);
            }

            if (array_key_exists('external_code', $data)) {
                $updateData['external_code'] = $data['external_code'] !== null ? trim((string) $data['external_code']) : null;
            }

            if (array_key_exists('external_name', $data)) {
                $updateData['external_name'] = $data['external_name'] !== null ? trim((string) $data['external_name']) : null;
            }

            if (array_key_exists('status', $data)) {
                $updateData['status'] = (bool) $data['status'];
            }

            if ($adminId !== null) {
                $updateData['updated_by'] = $adminId;
            }

            $mapping->update($updateData);

            return $mapping->fresh(['exchange:id,name,code,slug', 'commodity:id,name,code,slug,unit', 'creator', 'updater']);
        });

        $newExchangeId = (int) $updatedMapping->exchange_id;
        $newCommodityId = (int) $updatedMapping->commodity_id;

        $exchangeIds = array_unique([$oldExchangeId, $newExchangeId]);
        $commodityIds = array_unique([$oldCommodityId, $newCommodityId]);

        $this->clearCache(null, $exchangeIds, null, $commodityIds);

        return $updatedMapping;
    }

    /**
     * Update mapping active/inactive status.
     */
    public function updateStatus(ExchangeCommodityMapping $mapping, bool $status, ?int $adminId = null): ExchangeCommodityMapping
    {
        $exchangeId = (int) $mapping->exchange_id;
        $commodityId = (int) $mapping->commodity_id;

        $updatedMapping = DB::transaction(function () use ($mapping, $status, $adminId) {
            $mapping->update([
                'status' => $status,
                'updated_by' => $adminId,
            ]);

            return $mapping->fresh(['exchange:id,name,code,slug', 'commodity:id,name,code,slug,unit']);
        });

        $this->clearCache($exchangeId, [], $commodityId, []);

        return $updatedMapping;
    }

    /**
     * Soft delete a single mapping safely with dependency check.
     *
     * @throws DomainException
     */
    public function deleteMapping(ExchangeCommodityMapping $mapping): void
    {
        if (ExchangeInstrument::query()->where('exchange_commodity_mapping_id', $mapping->id)->exists()) {
            throw new DomainException('Commodity mapping cannot be deleted because it has exchange instruments assigned.');
        }

        $exchangeId = (int) $mapping->exchange_id;
        $commodityId = (int) $mapping->commodity_id;

        DB::transaction(function () use ($mapping) {
            $mapping->delete();
        });

        $this->clearCache($exchangeId, [], $commodityId, []);
    }

    /**
     * Invalidate mapping options caches.
     *
     * @param  int|null  $exchangeId Single exchange ID
     * @param  list<int>  $exchangeIds List of exchange IDs
     * @param  int|null  $commodityId Single commodity ID
     * @param  list<int>  $commodityIds List of commodity IDs
     */
    public function clearCache(?int $exchangeId = null, array $exchangeIds = [], ?int $commodityId = null, array $commodityIds = []): void
    {
        try {
            Cache::forget(self::CACHE_KEY_OPTIONS_ALL);

            if ($exchangeId !== null) {
                Cache::forget(self::CACHE_KEY_OPTIONS_EXCHANGE_PREFIX.$exchangeId);
            }

            foreach ($exchangeIds as $eId) {
                if ($eId) {
                    Cache::forget(self::CACHE_KEY_OPTIONS_EXCHANGE_PREFIX.$eId);
                }
            }

            if ($commodityId !== null) {
                Cache::forget(self::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$commodityId);
            }

            foreach ($commodityIds as $cId) {
                if ($cId) {
                    Cache::forget(self::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$cId);
                }
            }
        } catch (\Throwable $e) {
            // Non-blocking cache exception
        }
    }
}
