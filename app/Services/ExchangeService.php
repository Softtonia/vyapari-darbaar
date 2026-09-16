<?php

namespace App\Services;

use App\Models\Exchange;
use App\Models\ExchangeCommodityMapping;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExchangeService
{
    /**
     * Cache key for exchange options dropdown.
     */
    public const CACHE_KEY_OPTIONS = 'exchanges:options';

    /**
     * Cache TTL in seconds (1 hour).
     */
    public const CACHE_TTL_SECONDS = 3600;

    /**
     * Get a paginated list of exchanges with dynamic filtering and sorting.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listExchanges(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = max(1, min(100, $perPage));

        $sortBy = (string) ($filters['sort_by'] ?? 'sort_order');
        $sortOrder = (string) ($filters['sort_order'] ?? 'asc');

        $query = Exchange::query()
            ->select([
                'id',
                'name',
                'slug',
                'code',
                'exchange_type',
                'timezone',
                'website',
                'default_data_delay_minutes',
                'sort_order',
                'status',
                'created_at',
                'updated_at',
            ])
            ->search($filters['search'] ?? null)
            ->status($filters['status'] ?? null)
            ->when(! empty($filters['exchange_type']), fn ($q) => $q->where('exchange_type', $filters['exchange_type']))
            ->sorted($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get lightweight, cached list of active exchanges for dropdown options.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOptions(): array
    {
        return Cache::remember(self::CACHE_KEY_OPTIONS, self::CACHE_TTL_SECONDS, function () {
            return Exchange::query()
                ->active()
                ->select(['id', 'name', 'slug', 'code', 'exchange_type'])
                ->orderBy('sort_order', 'asc')
                ->orderBy('id', 'asc')
                ->get()
                ->toArray();
        });
    }

    /**
     * Create a new exchange.
     *
     * @param  array<string, mixed>  $data
     */
    public function createExchange(array $data, ?int $adminId = null): Exchange
    {
        $exchange = DB::transaction(function () use ($data, $adminId) {
            $slug = ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['name']);
            $slug = $this->generateUniqueSlug($slug);

            $code = strtoupper(trim((string) $data['code']));

            return Exchange::create([
                'name' => $data['name'],
                'slug' => $slug,
                'code' => $code,
                'exchange_type' => $data['exchange_type'],
                'timezone' => $data['timezone'] ?? 'Asia/Kolkata',
                'website' => $data['website'] ?? null,
                'default_data_delay_minutes' => array_key_exists('default_data_delay_minutes', $data) ? $data['default_data_delay_minutes'] : null,
                'sort_order' => $data['sort_order'] ?? 0,
                'status' => $data['status'] ?? true,
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]);
        });

        $this->clearCache();

        return $exchange;
    }

    /**
     * Update an existing exchange.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateExchange(Exchange $exchange, array $data, ?int $adminId = null): Exchange
    {
        $updatedExchange = DB::transaction(function () use ($exchange, $data, $adminId) {
            $updateData = [];

            if (array_key_exists('name', $data)) {
                $updateData['name'] = $data['name'];
            }

            if (array_key_exists('slug', $data) && ! empty($data['slug'])) {
                $updateData['slug'] = Str::slug($data['slug']);
            }

            if (array_key_exists('code', $data)) {
                $updateData['code'] = strtoupper(trim((string) $data['code']));
            }

            if (array_key_exists('exchange_type', $data)) {
                $updateData['exchange_type'] = $data['exchange_type'];
            }

            if (array_key_exists('timezone', $data)) {
                $updateData['timezone'] = $data['timezone'];
            }

            if (array_key_exists('website', $data)) {
                $updateData['website'] = $data['website'];
            }

            if (array_key_exists('default_data_delay_minutes', $data)) {
                $updateData['default_data_delay_minutes'] = $data['default_data_delay_minutes'];
            }

            if (array_key_exists('sort_order', $data)) {
                $updateData['sort_order'] = (int) $data['sort_order'];
            }

            if (array_key_exists('status', $data)) {
                $updateData['status'] = (bool) $data['status'];
            }

            if ($adminId !== null) {
                $updateData['updated_by'] = $adminId;
            }

            $exchange->update($updateData);

            return $exchange->fresh(['creator', 'updater']);
        });

        $this->clearCache();

        return $updatedExchange;
    }

    /**
     * Update exchange active/inactive status.
     */
    public function updateStatus(Exchange $exchange, bool $status, ?int $adminId = null): Exchange
    {
        $updatedExchange = DB::transaction(function () use ($exchange, $status, $adminId) {
            $exchange->update([
                'status' => $status,
                'updated_by' => $adminId,
            ]);

            return $exchange->fresh();
        });

        $this->clearCache();

        return $updatedExchange;
    }

    /**
     * Bulk update status for multiple exchanges.
     *
     * @param  list<int>  $ids
     */
    public function bulkUpdateStatus(array $ids, bool $status, ?int $adminId = null): int
    {
        if (empty($ids)) {
            return 0;
        }

        $updatedCount = DB::transaction(function () use ($ids, $status, $adminId) {
            return Exchange::query()
                ->whereIn('id', $ids)
                ->update([
                    'status' => $status,
                    'updated_by' => $adminId,
                ]);
        });

        $this->clearCache();

        return $updatedCount;
    }

    /**
     * Soft delete a single exchange safely with dependency check.
     *
     * @throws DomainException
     */
    public function deleteExchange(Exchange $exchange): void
    {
        if (ExchangeCommodityMapping::query()->where('exchange_id', $exchange->id)->exists()) {
            throw new DomainException('Exchange cannot be deleted because it has commodity mappings assigned.');
        }

        DB::transaction(function () use ($exchange) {
            $exchange->delete();
        });

        $this->clearCache();
    }

    /**
     * Bulk soft delete multiple exchanges atomically.
     *
     * @param  list<int>  $ids
     * @return array{deleted_count: int, blocked_ids: list<int>}
     */
    public function bulkDeleteExchanges(array $ids): array
    {
        if (empty($ids)) {
            return ['deleted_count' => 0, 'blocked_ids' => []];
        }

        $blockedIds = ExchangeCommodityMapping::query()
            ->whereIn('exchange_id', $ids)
            ->distinct()
            ->pluck('exchange_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->toArray();

        if (! empty($blockedIds)) {
            return [
                'deleted_count' => 0,
                'blocked_ids' => $blockedIds,
            ];
        }

        $deletedCount = DB::transaction(function () use ($ids) {
            return Exchange::query()
                ->whereIn('id', $ids)
                ->delete();
        });

        $this->clearCache();

        return [
            'deleted_count' => $deletedCount,
            'blocked_ids' => [],
        ];
    }

    /**
     * Generate a deterministic unique slug ensuring uniqueness globally (including soft deleted rows).
     */
    public function generateUniqueSlug(string $baseSlug, ?int $ignoreId = null): string
    {
        $slug = Str::slug($baseSlug);
        $originalSlug = $slug;
        $counter = 2;

        while (
            Exchange::query()
                ->withTrashed()
                ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$originalSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * Invalidate exchange options cache.
     */
    public function clearCache(): void
    {
        try {
            Cache::forget(self::CACHE_KEY_OPTIONS);
        } catch (\Throwable $e) {
            // Non-blocking cache exception
        }
    }
}
