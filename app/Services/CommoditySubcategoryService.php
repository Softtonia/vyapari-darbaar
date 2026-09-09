<?php

namespace App\Services;

use App\Models\CommodityGrade;
use App\Models\CommoditySubcategory;
use App\Models\CommodityVariety;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommoditySubcategoryService
{
    /**
     * Cache key prefix for options.
     */
    public const CACHE_KEY_OPTIONS_ALL = 'commodity_subcategories:options:all';

    public const CACHE_KEY_OPTIONS_COMMODITY_PREFIX = 'commodity_subcategories:options:commodity:';

    /**
     * Cache TTL in seconds (1 hour).
     */
    public const CACHE_TTL_SECONDS = 3600;

    /**
     * Get a paginated list of commodity subcategories with dynamic filtering and sorting.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listCommoditySubcategories(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = max(1, min(100, $perPage));

        $sortBy = (string) ($filters['sort_by'] ?? 'sort_order');
        $sortOrder = (string) ($filters['sort_order'] ?? 'asc');

        $query = CommoditySubcategory::query()
            ->with([
                'commodity:id,commodity_category_id,name_en,name_hi,slug',
                'commodity.category:id,name_en,name_hi,slug',
            ])
            ->select([
                'id',
                'commodity_id',
                'name_en',
                'name_hi',
                'slug',
                'sort_order',
                'status',
                'created_at',
                'updated_at',
            ])
            ->search($filters['search'] ?? null)
            ->commodity($filters['commodity_id'] ?? null)
            ->category($filters['commodity_category_id'] ?? null)
            ->status($filters['status'] ?? null)
            ->sorted($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get lightweight, cached list of active commodity subcategories for dropdown options.
     * Requires subcategory, commodity, and parent category to all be active and non-deleted.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOptions(?int $commodityId = null): array
    {
        $cacheKey = $commodityId !== null
            ? self::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$commodityId
            : self::CACHE_KEY_OPTIONS_ALL;

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($commodityId) {
            return CommoditySubcategory::query()
                ->active()
                ->whereHas('commodity', function ($query) {
                    $query->active()->whereHas('category', function ($q) {
                        $q->active();
                    });
                })
                ->when($commodityId !== null, fn ($q) => $q->where('commodity_id', $commodityId))
                ->select(['id', 'commodity_id', 'name_en', 'name_hi', 'slug'])
                ->orderBy('sort_order', 'asc')
                ->orderBy('id', 'asc')
                ->get()
                ->toArray();
        });
    }

    /**
     * Create a new commodity subcategory.
     *
     * @param  array<string, mixed>  $data
     */
    public function createCommoditySubcategory(array $data, ?int $adminId = null): CommoditySubcategory
    {
        $commodityId = (int) $data['commodity_id'];

        $subcat = DB::transaction(function () use ($data, $commodityId, $adminId) {
            $slug = ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['name_en']);
            $slug = $this->generateUniqueSlug($slug, $commodityId);

            return CommoditySubcategory::create([
                'commodity_id' => $commodityId,
                'name_en' => $data['name_en'],
                'name_hi' => $data['name_hi'] ?? null,
                'slug' => $slug,
                'description_en' => $data['description_en'] ?? null,
                'description_hi' => $data['description_hi'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
                'status' => $data['status'] ?? true,
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]);
        });

        $this->clearCache($commodityId, [], (int) $subcat->id);

        return $subcat->load([
            'commodity:id,commodity_category_id,name_en,name_hi,slug',
            'commodity.category:id,name_en,name_hi,slug',
        ]);
    }

    /**
     * Update an existing commodity subcategory.
     *
     * @param  array<string, mixed>  $data
     * @throws DomainException
     */
    public function updateCommoditySubcategory(CommoditySubcategory $commoditySubcategory, array $data, ?int $adminId = null): CommoditySubcategory
    {
        $oldCommodityId = (int) $commoditySubcategory->commodity_id;

        // Subcategory Reparenting Protection: If moving to another commodity, ensure no non-deleted varieties or grades are assigned
        if (array_key_exists('commodity_id', $data) && (int) $data['commodity_id'] !== $oldCommodityId) {
            $hasVarieties = CommodityVariety::query()->where('commodity_subcategory_id', $commoditySubcategory->id)->exists();
            $hasGrades = CommodityGrade::query()->where('commodity_subcategory_id', $commoditySubcategory->id)->exists();
            if ($hasVarieties || $hasGrades) {
                throw new DomainException('Commodity subcategory cannot be moved because varieties or grades are assigned to it.');
            }
        }

        $updatedSubcategory = DB::transaction(function () use ($commoditySubcategory, $data, $adminId) {
            $updateData = [];

            if (array_key_exists('commodity_id', $data)) {
                $updateData['commodity_id'] = (int) $data['commodity_id'];
            }

            if (array_key_exists('name_en', $data)) {
                $updateData['name_en'] = $data['name_en'];
            }

            if (array_key_exists('name_hi', $data)) {
                $updateData['name_hi'] = $data['name_hi'];
            }

            // If slug is explicitly supplied, normalize and update; otherwise retain old slug
            if (array_key_exists('slug', $data) && ! empty($data['slug'])) {
                $updateData['slug'] = Str::slug($data['slug']);
            }

            if (array_key_exists('description_en', $data)) {
                $updateData['description_en'] = $data['description_en'];
            }

            if (array_key_exists('description_hi', $data)) {
                $updateData['description_hi'] = $data['description_hi'];
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

            $commoditySubcategory->update($updateData);

            return $commoditySubcategory->fresh([
                'commodity:id,commodity_category_id,name_en,name_hi,slug',
                'commodity.category:id,name_en,name_hi,slug',
                'creator',
                'updater',
            ]);
        });

        $newCommodityId = (int) $updatedSubcategory->commodity_id;
        $affectedCommodityIds = array_unique([$oldCommodityId, $newCommodityId]);
        $this->clearCache(null, $affectedCommodityIds, (int) $updatedSubcategory->id);

        return $updatedSubcategory;
    }

    /**
     * Update commodity subcategory active/inactive status.
     */
    public function updateStatus(CommoditySubcategory $commoditySubcategory, bool $status, ?int $adminId = null): CommoditySubcategory
    {
        $commodityId = (int) $commoditySubcategory->commodity_id;
        $subcategoryId = (int) $commoditySubcategory->id;

        $updatedSubcategory = DB::transaction(function () use ($commoditySubcategory, $status, $adminId) {
            $commoditySubcategory->update([
                'status' => $status,
                'updated_by' => $adminId,
            ]);

            return $commoditySubcategory->fresh([
                'commodity:id,commodity_category_id,name_en,name_hi,slug',
                'commodity.category:id,name_en,name_hi,slug',
            ]);
        });

        $this->clearCache($commodityId, [], $subcategoryId);

        return $updatedSubcategory;
    }

    /**
     * Bulk update status for multiple commodity subcategories across potentially multiple commodities.
     *
     * @param  list<int>  $ids
     */
    public function bulkUpdateStatus(array $ids, bool $status, ?int $adminId = null): int
    {
        if (empty($ids)) {
            return 0;
        }

        // Retrieve affected commodity IDs before mutation
        $affectedCommodityIds = CommoditySubcategory::query()
            ->whereIn('id', $ids)
            ->distinct()
            ->pluck('commodity_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $updatedCount = DB::transaction(function () use ($ids, $status, $adminId) {
            return CommoditySubcategory::query()
                ->whereIn('id', $ids)
                ->update([
                    'status' => $status,
                    'updated_by' => $adminId,
                ]);
        });

        $this->clearCache(null, $affectedCommodityIds, null, $ids);

        return $updatedCount;
    }

    /**
     * Soft delete a single commodity subcategory safely.
     *
     * @throws DomainException
     */
    public function deleteCommoditySubcategory(CommoditySubcategory $commoditySubcategory): void
    {
        if (
            CommodityVariety::query()->where('commodity_subcategory_id', $commoditySubcategory->id)->exists() ||
            CommodityGrade::query()->where('commodity_subcategory_id', $commoditySubcategory->id)->exists()
        ) {
            throw new DomainException('Commodity subcategory cannot be deleted because it has varieties or grades assigned.');
        }

        $commodityId = (int) $commoditySubcategory->commodity_id;
        $subcategoryId = (int) $commoditySubcategory->id;

        DB::transaction(function () use ($commoditySubcategory) {
            $commoditySubcategory->delete();
        });

        $this->clearCache($commodityId, [], $subcategoryId);
    }

    /**
     * Bulk soft delete multiple commodity subcategories atomically across potentially multiple commodities.
     *
     * @param  list<int>  $ids
     * @return array{deleted_count: int, blocked_ids: list<int>}
     */
    public function bulkDeleteCommoditySubcategories(array $ids): array
    {
        if (empty($ids)) {
            return ['deleted_count' => 0, 'blocked_ids' => []];
        }

        // Check if any subcategory has assigned non-deleted varieties
        $varietyBlockedIds = CommodityVariety::query()
            ->whereIn('commodity_subcategory_id', $ids)
            ->distinct()
            ->pluck('commodity_subcategory_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        // Check if any subcategory has assigned non-deleted grades
        $gradeBlockedIds = CommodityGrade::query()
            ->whereIn('commodity_subcategory_id', $ids)
            ->distinct()
            ->pluck('commodity_subcategory_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $blockedIds = array_values(array_unique(array_merge($varietyBlockedIds, $gradeBlockedIds)));
        sort($blockedIds);

        if (! empty($blockedIds)) {
            return [
                'deleted_count' => 0,
                'blocked_ids' => $blockedIds,
            ];
        }

        // Retrieve affected commodity IDs before mutation
        $affectedCommodityIds = CommoditySubcategory::query()
            ->whereIn('id', $ids)
            ->distinct()
            ->pluck('commodity_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $deletedCount = DB::transaction(function () use ($ids) {
            return CommoditySubcategory::query()
                ->whereIn('id', $ids)
                ->delete();
        });

        $this->clearCache(null, $affectedCommodityIds, null, $ids);

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
            CommoditySubcategory::query()
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
     * Invalidate commodity subcategory and cross-module variety options caches.
     *
     * @param  int|null  $commodityId Single commodity ID to invalidate
     * @param  list<int>  $commodityIds List of commodity IDs to invalidate
     * @param  int|null  $subcategoryId Single subcategory ID to invalidate
     * @param  list<int>  $subcategoryIds List of subcategory IDs to invalidate
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

            // Cross-module cache invalidation for CommodityVarieties
            Cache::forget(CommodityVarietyService::CACHE_KEY_OPTIONS_ALL);

            if ($commodityId !== null) {
                Cache::forget(CommodityVarietyService::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$commodityId);
            }

            foreach ($commodityIds as $commId) {
                if ($commId) {
                    Cache::forget(CommodityVarietyService::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$commId);
                }
            }

            if ($subcategoryId !== null) {
                Cache::forget(CommodityVarietyService::CACHE_KEY_OPTIONS_SUBCATEGORY_PREFIX.$subcategoryId);
            }

            foreach ($subcategoryIds as $subId) {
                if ($subId) {
                    Cache::forget(CommodityVarietyService::CACHE_KEY_OPTIONS_SUBCATEGORY_PREFIX.$subId);
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
