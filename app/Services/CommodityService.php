<?php

namespace App\Services;

use App\Models\Commodity;
use App\Models\CommodityGrade;
use App\Models\CommoditySubcategory;
use App\Models\CommodityVariety;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommodityService
{
    /**
     * Cache key prefix for options.
     */
    public const CACHE_KEY_OPTIONS_ALL = 'commodities:options:all';

    public const CACHE_KEY_OPTIONS_CATEGORY_PREFIX = 'commodities:options:category:';

    /**
     * Cache TTL in seconds (1 hour).
     */
    public const CACHE_TTL_SECONDS = 3600;

    /**
     * Get a paginated list of commodities with dynamic filtering and sorting.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listCommodities(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = max(1, min(100, $perPage));

        $sortBy = (string) ($filters['sort_by'] ?? 'sort_order');
        $sortOrder = (string) ($filters['sort_order'] ?? 'asc');

        $query = Commodity::query()
            ->with(['category:id,name,slug'])
            ->select([
                'id',
                'commodity_category_id',
                'name',
                'slug',
                'sort_order',
                'status',
                'created_at',
                'updated_at',
            ])
            ->search($filters['search'] ?? null)
            ->category($filters['commodity_category_id'] ?? null)
            ->status($filters['status'] ?? null)
            ->sorted($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get lightweight, cached list of active commodities for dropdown options.
     * Only returns commodities whose parent category is also active.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOptions(?int $categoryId = null): array
    {
        $cacheKey = $categoryId !== null
            ? self::CACHE_KEY_OPTIONS_CATEGORY_PREFIX.$categoryId
            : self::CACHE_KEY_OPTIONS_ALL;

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($categoryId) {
            return Commodity::query()
                ->active()
                ->whereHas('category', function ($query) {
                    $query->active();
                })
                ->when($categoryId !== null, fn ($q) => $q->where('commodity_category_id', $categoryId))
                ->select(['id', 'commodity_category_id', 'name', 'slug'])
                ->orderBy('sort_order', 'asc')
                ->orderBy('id', 'asc')
                ->get()
                ->toArray();
        });
    }

    /**
     * Create a new commodity.
     *
     * @param  array<string, mixed>  $data
     */
    public function createCommodity(array $data, ?int $adminId = null): Commodity
    {
        $commodity = DB::transaction(function () use ($data, $adminId) {
            $slug = ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['name']);
            $slug = $this->generateUniqueSlug($slug);

            return Commodity::create([
                'commodity_category_id' => (int) $data['commodity_category_id'],
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
                'status' => $data['status'] ?? true,
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]);
        });

        $this->clearCache((int) $commodity->commodity_category_id, [], (int) $commodity->id);

        return $commodity->load(['category:id,name,slug']);
    }

    /**
     * Update an existing commodity.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateCommodity(Commodity $commodity, array $data, ?int $adminId = null): Commodity
    {
        $oldCategoryId = (int) $commodity->commodity_category_id;

        $updatedCommodity = DB::transaction(function () use ($commodity, $data, $adminId) {
            $updateData = [];

            if (array_key_exists('commodity_category_id', $data)) {
                $updateData['commodity_category_id'] = (int) $data['commodity_category_id'];
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

            $commodity->update($updateData);

            return $commodity->fresh(['category:id,name,slug', 'creator', 'updater']);
        });

        $newCategoryId = (int) $updatedCommodity->commodity_category_id;
        $categoryIds = array_unique([$oldCategoryId, $newCategoryId]);
        $this->clearCache(null, $categoryIds, (int) $updatedCommodity->id);

        return $updatedCommodity;
    }

    /**
     * Update commodity active/inactive status.
     */
    public function updateStatus(Commodity $commodity, bool $status, ?int $adminId = null): Commodity
    {
        $categoryId = (int) $commodity->commodity_category_id;

        $updatedCommodity = DB::transaction(function () use ($commodity, $status, $adminId) {
            $commodity->update([
                'status' => $status,
                'updated_by' => $adminId,
            ]);

            return $commodity->fresh(['category:id,name,slug']);
        });

        $this->clearCache($categoryId, [], (int) $updatedCommodity->id);

        return $updatedCommodity;
    }

    /**
     * Bulk update status for multiple commodities across potentially multiple categories.
     *
     * @param  list<int>  $ids
     */
    public function bulkUpdateStatus(array $ids, bool $status, ?int $adminId = null): int
    {
        if (empty($ids)) {
            return 0;
        }

        // Retrieve affected category IDs before mutation
        $affectedCategoryIds = Commodity::query()
            ->whereIn('id', $ids)
            ->distinct()
            ->pluck('commodity_category_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $updatedCount = DB::transaction(function () use ($ids, $status, $adminId) {
            return Commodity::query()
                ->whereIn('id', $ids)
                ->update([
                    'status' => $status,
                    'updated_by' => $adminId,
                ]);
        });

        $this->clearCache(null, $affectedCategoryIds, null, $ids);

        return $updatedCount;
    }

    /**
     * Soft delete a single commodity safely.
     *
     * @throws DomainException
     */
    public function deleteCommodity(Commodity $commodity): void
    {
        if (
            CommoditySubcategory::query()->where('commodity_id', $commodity->id)->exists() ||
            CommodityVariety::query()->where('commodity_id', $commodity->id)->exists() ||
            CommodityGrade::query()->where('commodity_id', $commodity->id)->exists()
        ) {
            throw new DomainException('Commodity cannot be deleted because it has subcategories, varieties, or grades assigned.');
        }

        $categoryId = (int) $commodity->commodity_category_id;
        $commodityId = (int) $commodity->id;

        DB::transaction(function () use ($commodity) {
            $commodity->delete();
        });

        $this->clearCache($categoryId, [], $commodityId);
    }

    /**
     * Bulk soft delete multiple commodities atomically across potentially multiple categories.
     *
     * @param  list<int>  $ids
     * @return array{deleted_count: int, blocked_ids: list<int>}
     */
    public function bulkDeleteCommodities(array $ids): array
    {
        if (empty($ids)) {
            return ['deleted_count' => 0, 'blocked_ids' => []];
        }

        // Check if any commodity has assigned subcategories
        $blockedSubcategoryIds = CommoditySubcategory::query()
            ->whereIn('commodity_id', $ids)
            ->distinct()
            ->pluck('commodity_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        // Check if any commodity has assigned direct or indirect varieties
        $blockedVarietyIds = CommodityVariety::query()
            ->whereIn('commodity_id', $ids)
            ->distinct()
            ->pluck('commodity_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        // Check if any commodity has assigned direct or indirect grades
        $blockedGradeIds = CommodityGrade::query()
            ->whereIn('commodity_id', $ids)
            ->distinct()
            ->pluck('commodity_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $blockedIds = array_values(array_unique(array_merge($blockedSubcategoryIds, $blockedVarietyIds, $blockedGradeIds)));
        sort($blockedIds);

        if (! empty($blockedIds)) {
            return [
                'deleted_count' => 0,
                'blocked_ids' => $blockedIds,
            ];
        }

        // Retrieve affected category IDs before mutation
        $affectedCategoryIds = Commodity::query()
            ->whereIn('id', $ids)
            ->distinct()
            ->pluck('commodity_category_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $deletedCount = DB::transaction(function () use ($ids) {
            return Commodity::query()
                ->whereIn('id', $ids)
                ->delete();
        });

        $this->clearCache(null, $affectedCategoryIds, null, $ids);

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
            Commodity::query()
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
     * Invalidate commodity options caches, subcategory options caches, and variety options caches.
     *
     * @param  int|null  $categoryId Single category ID to invalidate
     * @param  list<int>  $categoryIds List of category IDs to invalidate
     * @param  int|null  $commodityId Single commodity ID to invalidate
     * @param  list<int>  $commodityIds List of commodity IDs to invalidate
     */
    public function clearCache(?int $categoryId = null, array $categoryIds = [], ?int $commodityId = null, array $commodityIds = []): void
    {
        try {
            Cache::forget(self::CACHE_KEY_OPTIONS_ALL);

            if ($categoryId !== null) {
                Cache::forget(self::CACHE_KEY_OPTIONS_CATEGORY_PREFIX.$categoryId);
            }

            foreach ($categoryIds as $catId) {
                if ($catId) {
                    Cache::forget(self::CACHE_KEY_OPTIONS_CATEGORY_PREFIX.$catId);
                }
            }

            // Cross-module cache invalidation for CommoditySubcategories
            Cache::forget(CommoditySubcategoryService::CACHE_KEY_OPTIONS_ALL);

            $allCommIds = array_values(array_filter(array_unique(array_merge(
                $commodityId !== null ? [$commodityId] : [],
                $commodityIds
            ))));

            if (! empty($allCommIds)) {
                foreach ($allCommIds as $commId) {
                    Cache::forget(CommoditySubcategoryService::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$commId);
                }

                // Invalidate subcategory options for varieties and variety options for all affected commodities
                $subcatIds = CommoditySubcategory::query()
                    ->whereIn('commodity_id', $allCommIds)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->toArray();

                Cache::forget(CommodityVarietyService::CACHE_KEY_OPTIONS_ALL);
                foreach ($allCommIds as $commId) {
                    Cache::forget(CommodityVarietyService::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$commId);
                }
                foreach ($subcatIds as $subId) {
                    Cache::forget(CommodityVarietyService::CACHE_KEY_OPTIONS_SUBCATEGORY_PREFIX.$subId);
                }

                // Cross-module cache invalidation for Commodity Grades
                Cache::forget(CommodityGradeService::CACHE_KEY_OPTIONS_ALL);
                foreach ($allCommIds as $commId) {
                    Cache::forget(CommodityGradeService::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$commId);
                }
                foreach ($subcatIds as $subId) {
                    Cache::forget(CommodityGradeService::CACHE_KEY_OPTIONS_SUBCATEGORY_PREFIX.$subId);
                }
            } else {
                Cache::forget(CommodityVarietyService::CACHE_KEY_OPTIONS_ALL);
                Cache::forget(CommodityGradeService::CACHE_KEY_OPTIONS_ALL);
            }
        } catch (\Throwable $e) {
            // Non-blocking cache exception
        }
    }
}

