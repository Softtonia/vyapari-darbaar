<?php

namespace App\Services;

use App\Models\CommodityGrade;
use App\Models\CommodityVariety;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommodityVarietyService
{
    /**
     * Cache key prefix for options.
     */
    public const CACHE_KEY_OPTIONS_ALL = 'commodity_varieties:options:all';

    public const CACHE_KEY_OPTIONS_COMMODITY_PREFIX = 'commodity_varieties:options:commodity:';

    public const CACHE_KEY_OPTIONS_SUBCATEGORY_PREFIX = 'commodity_varieties:options:subcategory:';

    /**
     * Cache TTL in seconds (1 hour).
     */
    public const CACHE_TTL_SECONDS = 3600;

    /**
     * Get a paginated list of commodity varieties with dynamic filtering and sorting.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listCommodityVarieties(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = max(1, min(100, $perPage));

        $sortBy = (string) ($filters['sort_by'] ?? 'sort_order');
        $sortOrder = (string) ($filters['sort_order'] ?? 'asc');

        $query = CommodityVariety::query()
            ->with([
                'commodity:id,commodity_category_id,name,slug',
                'commodity.category:id,name,slug',
                'subcategory:id,commodity_id,name,slug',
            ])
            ->select([
                'id',
                'commodity_id',
                'commodity_subcategory_id',
                'name',
                'slug',
                'sort_order',
                'status',
                'created_at',
                'updated_at',
            ])
            ->search($filters['search'] ?? null)
            ->commodity($filters['commodity_id'] ?? null)
            ->subcategory($filters['commodity_subcategory_id'] ?? null)
            ->category($filters['commodity_category_id'] ?? null)
            ->status($filters['status'] ?? null)
            ->sorted($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get lightweight, cached list of active commodity varieties for dropdown options.
     * Requires variety, commodity, and parent category to all be active and non-deleted.
     * If assigned to a subcategory, the subcategory must also be active and non-deleted.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOptions(?int $commodityId = null, ?int $subcategoryId = null): array
    {
        if ($subcategoryId !== null) {
            $cacheKey = self::CACHE_KEY_OPTIONS_SUBCATEGORY_PREFIX.$subcategoryId;
        } elseif ($commodityId !== null) {
            $cacheKey = self::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$commodityId;
        } else {
            $cacheKey = self::CACHE_KEY_OPTIONS_ALL;
        }

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($commodityId, $subcategoryId) {
            return CommodityVariety::query()
                ->active()
                ->whereHas('commodity', function (Builder $query) {
                    $query->active()->whereHas('category', function (Builder $q) {
                        $q->active();
                    });
                })
                ->where(function (Builder $query) {
                    $query->whereNull('commodity_subcategory_id')
                        ->orWhereHas('subcategory', function (Builder $q) {
                            $q->active();
                        });
                })
                ->when($commodityId !== null, fn (Builder $q) => $q->where('commodity_id', $commodityId))
                ->when($subcategoryId !== null, fn (Builder $q) => $q->where('commodity_subcategory_id', $subcategoryId))
                ->select(['id', 'commodity_id', 'commodity_subcategory_id', 'name', 'slug'])
                ->orderBy('sort_order', 'asc')
                ->orderBy('id', 'asc')
                ->get()
                ->toArray();
        });
    }

    /**
     * Create a new commodity variety.
     *
     * @param  array<string, mixed>  $data
     */
    public function createCommodityVariety(array $data, ?int $adminId = null): CommodityVariety
    {
        $commodityId = (int) $data['commodity_id'];
        $subcategoryId = ! empty($data['commodity_subcategory_id']) ? (int) $data['commodity_subcategory_id'] : null;

        $variety = DB::transaction(function () use ($data, $commodityId, $subcategoryId, $adminId) {
            $slug = ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['name']);
            $slug = $this->generateUniqueSlug($slug, $commodityId);

            return CommodityVariety::create([
                'commodity_id' => $commodityId,
                'commodity_subcategory_id' => $subcategoryId,
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
                'status' => $data['status'] ?? true,
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]);
        });

        $this->clearCache($commodityId, [], $subcategoryId, []);

        return $variety->load([
            'commodity:id,commodity_category_id,name,slug',
            'commodity.category:id,name,slug',
            'subcategory:id,commodity_id,name,slug',
        ]);
    }

    /**
     * Update an existing commodity variety.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateCommodityVariety(CommodityVariety $commodityVariety, array $data, ?int $adminId = null): CommodityVariety
    {
        $oldCommodityId = (int) $commodityVariety->commodity_id;
        $oldSubcategoryId = $commodityVariety->commodity_subcategory_id ? (int) $commodityVariety->commodity_subcategory_id : null;

        // Reparenting Protection: blocked if any non-deleted Grade references this variety
        $isMovingCommodity = array_key_exists('commodity_id', $data) && (int) $data['commodity_id'] !== $oldCommodityId;
        $isMovingSubcategory = array_key_exists('commodity_subcategory_id', $data) && ($data['commodity_subcategory_id'] !== null ? (int) $data['commodity_subcategory_id'] : null) !== $oldSubcategoryId;

        if ($isMovingCommodity || $isMovingSubcategory) {
            if (CommodityGrade::query()->where('commodity_variety_id', $commodityVariety->id)->exists()) {
                throw new DomainException('Commodity variety cannot be moved because grades are assigned to it.');
            }
        }

        $updatedVariety = DB::transaction(function () use ($commodityVariety, $data, $adminId) {
            $updateData = [];

            if (array_key_exists('commodity_id', $data)) {
                $updateData['commodity_id'] = (int) $data['commodity_id'];
            }

            if (array_key_exists('commodity_subcategory_id', $data)) {
                $updateData['commodity_subcategory_id'] = $data['commodity_subcategory_id'] !== null ? (int) $data['commodity_subcategory_id'] : null;
            }

            if (array_key_exists('name', $data)) {
                $updateData['name'] = $data['name'];
            }

            // If slug is explicitly supplied, normalize and update; otherwise retain old slug
            if (array_key_exists('slug', $data) && ! empty($data['slug'])) {
                $updateData['slug'] = Str::slug($data['slug']);
            }

            if (array_key_exists('description', $data)) {
                $updateData['description'] = $data['description'];
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

            $commodityVariety->update($updateData);

            return $commodityVariety->fresh([
                'commodity:id,commodity_category_id,name,slug',
                'commodity.category:id,name,slug',
                'subcategory:id,commodity_id,name,slug',
                'creator',
                'updater',
            ]);
        });

        $newCommodityId = (int) $updatedVariety->commodity_id;
        $newSubcategoryId = $updatedVariety->commodity_subcategory_id ? (int) $updatedVariety->commodity_subcategory_id : null;

        $affectedCommodityIds = array_values(array_filter(array_unique([$oldCommodityId, $newCommodityId])));
        $affectedSubcategoryIds = array_values(array_filter(array_unique([$oldSubcategoryId, $newSubcategoryId])));

        $this->clearCache(null, $affectedCommodityIds, null, $affectedSubcategoryIds);

        return $updatedVariety;
    }

    /**
     * Update commodity variety active/inactive status.
     */
    public function updateStatus(CommodityVariety $commodityVariety, bool $status, ?int $adminId = null): CommodityVariety
    {
        $commodityId = (int) $commodityVariety->commodity_id;
        $subcategoryId = $commodityVariety->commodity_subcategory_id ? (int) $commodityVariety->commodity_subcategory_id : null;

        $updatedVariety = DB::transaction(function () use ($commodityVariety, $status, $adminId) {
            $commodityVariety->update([
                'status' => $status,
                'updated_by' => $adminId,
            ]);

            return $commodityVariety->fresh([
                'commodity:id,commodity_category_id,name,slug',
                'commodity.category:id,name,slug',
                'subcategory:id,commodity_id,name,slug',
            ]);
        });

        $this->clearCache($commodityId, [], $subcategoryId, []);

        return $updatedVariety;
    }

    /**
     * Bulk update status for multiple commodity varieties.
     *
     * @param  list<int>  $ids
     */
    public function bulkUpdateStatus(array $ids, bool $status, ?int $adminId = null): int
    {
        if (empty($ids)) {
            return 0;
        }

        // Retrieve distinct affected commodity IDs and subcategory IDs before mutation
        $affectedRows = CommodityVariety::query()
            ->whereIn('id', $ids)
            ->get(['commodity_id', 'commodity_subcategory_id']);

        $affectedCommodityIds = $affectedRows->pluck('commodity_id')->filter()->unique()->map(fn ($id) => (int) $id)->values()->toArray();
        $affectedSubcategoryIds = $affectedRows->pluck('commodity_subcategory_id')->filter()->unique()->map(fn ($id) => (int) $id)->values()->toArray();

        $updatedCount = DB::transaction(function () use ($ids, $status, $adminId) {
            return CommodityVariety::query()
                ->whereIn('id', $ids)
                ->update([
                    'status' => $status,
                    'updated_by' => $adminId,
                ]);
        });

        $this->clearCache(null, $affectedCommodityIds, null, $affectedSubcategoryIds);

        return $updatedCount;
    }

    /**
     * Soft delete a single commodity variety safely.
     */
    public function deleteCommodityVariety(CommodityVariety $commodityVariety): void
    {
        if (CommodityGrade::query()->where('commodity_variety_id', $commodityVariety->id)->exists()) {
            throw new DomainException('Commodity variety cannot be deleted because it has grades assigned.');
        }

        $commodityId = (int) $commodityVariety->commodity_id;
        $subcategoryId = $commodityVariety->commodity_subcategory_id ? (int) $commodityVariety->commodity_subcategory_id : null;

        DB::transaction(function () use ($commodityVariety) {
            $commodityVariety->delete();
        });

        $this->clearCache($commodityId, [], $subcategoryId, []);
    }

    /**
     * Bulk soft delete multiple commodity varieties atomically in a single transaction.
     *
     * @param  list<int>  $ids
     * @return array{deleted_count: int, blocked_ids: list<int>}
     */
    public function bulkDeleteCommodityVarieties(array $ids): array
    {
        if (empty($ids)) {
            return ['deleted_count' => 0, 'blocked_ids' => []];
        }

        // Check if any variety has assigned non-deleted grades
        $blockedIds = CommodityGrade::query()
            ->whereIn('commodity_variety_id', $ids)
            ->distinct()
            ->pluck('commodity_variety_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        if (! empty($blockedIds)) {
            return [
                'deleted_count' => 0,
                'blocked_ids' => array_values($blockedIds),
            ];
        }

        // Retrieve distinct affected commodity IDs and subcategory IDs before mutation
        $affectedRows = CommodityVariety::query()
            ->whereIn('id', $ids)
            ->get(['commodity_id', 'commodity_subcategory_id']);

        $affectedCommodityIds = $affectedRows->pluck('commodity_id')->filter()->unique()->map(fn ($id) => (int) $id)->values()->toArray();
        $affectedSubcategoryIds = $affectedRows->pluck('commodity_subcategory_id')->filter()->unique()->map(fn ($id) => (int) $id)->values()->toArray();

        $deletedCount = DB::transaction(function () use ($ids) {
            return CommodityVariety::query()
                ->whereIn('id', $ids)
                ->delete();
        });

        $this->clearCache(null, $affectedCommodityIds, null, $affectedSubcategoryIds);

        return [
            'deleted_count' => $deletedCount,
            'blocked_ids' => [],
        ];
    }

    /**
     * Generate a deterministic unique slug ensuring uniqueness per-commodity (including soft deleted rows).
     */
    public function generateUniqueSlug(string $baseSlug, int $commodityId, ?int $ignoreId = null): string
    {
        $slug = Str::slug($baseSlug);
        $originalSlug = $slug;
        $counter = 2;

        while (
            CommodityVariety::query()
                ->withTrashed()
                ->where('commodity_id', $commodityId)
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
     * Invalidate commodity variety options caches.
     *
     * @param  int|null  $commodityId Single commodity ID
     * @param  list<int>  $commodityIds List of commodity IDs
     * @param  int|null  $subcategoryId Single subcategory ID
     * @param  list<int>  $subcategoryIds List of subcategory IDs
     */
    public function clearCache(
        ?int $commodityId = null,
        array $commodityIds = [],
        ?int $subcategoryId = null,
        array $subcategoryIds = []
    ): void {
        try {
            Cache::forget(self::CACHE_KEY_OPTIONS_ALL);

            if ($commodityId !== null) {
                Cache::forget(self::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$commodityId);
            }

            foreach ($commodityIds as $commId) {
                if ($commId) {
                    Cache::forget(self::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$commId);
                }
            }

            if ($subcategoryId !== null) {
                Cache::forget(self::CACHE_KEY_OPTIONS_SUBCATEGORY_PREFIX.$subcategoryId);
            }

            foreach ($subcategoryIds as $subId) {
                if ($subId) {
                    Cache::forget(self::CACHE_KEY_OPTIONS_SUBCATEGORY_PREFIX.$subId);
                }
            }

            // Cross-module cache invalidation for CommodityGrades
            Cache::forget(CommodityGradeService::CACHE_KEY_OPTIONS_ALL);

            if ($commodityId !== null) {
                Cache::forget(CommodityGradeService::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$commodityId);
            }

            foreach ($commodityIds as $commId) {
                if ($commId) {
                    Cache::forget(CommodityGradeService::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$commId);
                }
            }

            if ($subcategoryId !== null) {
                Cache::forget(CommodityGradeService::CACHE_KEY_OPTIONS_SUBCATEGORY_PREFIX.$subcategoryId);
            }

            foreach ($subcategoryIds as $subId) {
                if ($subId) {
                    Cache::forget(CommodityGradeService::CACHE_KEY_OPTIONS_SUBCATEGORY_PREFIX.$subId);
                }
            }
        } catch (\Throwable $e) {
            // Non-blocking cache exception
        }
    }
}
