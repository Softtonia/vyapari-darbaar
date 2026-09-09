<?php

namespace App\Services;

use App\Models\Commodity;
use App\Models\CommodityCategory;
use App\Models\CommoditySubcategory;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommodityCategoryService
{
    /**
     * Cache key for active dropdown options.
     */
    public const CACHE_KEY_OPTIONS = 'commodity_categories:options';

    /**
     * Cache key for active categories list.
     */
    public const CACHE_KEY_ACTIVE = 'commodity_categories:active';

    /**
     * Cache TTL in seconds (1 hour).
     */
    public const CACHE_TTL_SECONDS = 3600;

    /**
     * Get a paginated list of commodity categories with dynamic filtering and sorting.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listCategories(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = max(1, min(100, $perPage));

        $sortBy = (string) ($filters['sort_by'] ?? 'sort_order');
        $sortOrder = (string) ($filters['sort_order'] ?? 'asc');

        $query = CommodityCategory::query()
            ->select([
                'id',
                'name_en',
                'name_hi',
                'slug',
                'description_en',
                'description_hi',
                'sort_order',
                'status',
                'created_by',
                'updated_by',
                'created_at',
                'updated_at',
            ])
            ->search($filters['search'] ?? null)
            ->status($filters['status'] ?? null)
            ->sorted($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get lightweight, cached list of active commodity categories for dropdowns.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOptions(): array
    {
        return Cache::remember(self::CACHE_KEY_OPTIONS, self::CACHE_TTL_SECONDS, function () {
            return CommodityCategory::query()
                ->active()
                ->select(['id', 'name_en', 'name_hi', 'slug'])
                ->orderBy('sort_order', 'asc')
                ->orderBy('id', 'asc')
                ->get()
                ->toArray();
        });
    }

    /**
     * Create a new commodity category.
     *
     * @param  array<string, mixed>  $data
     */
    public function createCategory(array $data, ?int $adminId = null): CommodityCategory
    {
        $category = DB::transaction(function () use ($data, $adminId) {
            $slug = ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['name_en']);
            $slug = $this->generateUniqueSlug($slug);

            return CommodityCategory::create([
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

        $this->clearCache((int) $category->id);

        return $category;
    }

    /**
     * Update an existing commodity category.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateCategory(CommodityCategory $category, array $data, ?int $adminId = null): CommodityCategory
    {
        $updatedCategory = DB::transaction(function () use ($category, $data, $adminId) {
            $updateData = [];

            if (array_key_exists('name_en', $data)) {
                $updateData['name_en'] = $data['name_en'];
            }

            if (array_key_exists('name_hi', $data)) {
                $updateData['name_hi'] = $data['name_hi'];
            }

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

            $category->update($updateData);

            return $category->fresh(['creator', 'updater']);
        });

        $this->clearCache((int) $updatedCategory->id);

        return $updatedCategory;
    }

    /**
     * Update category active/inactive status.
     */
    public function updateStatus(CommodityCategory $category, bool $status, ?int $adminId = null): CommodityCategory
    {
        $updatedCategory = DB::transaction(function () use ($category, $status, $adminId) {
            $category->update([
                'status' => $status,
                'updated_by' => $adminId,
            ]);

            return $category->fresh();
        });

        $this->clearCache((int) $updatedCategory->id);

        return $updatedCategory;
    }

    /**
     * Bulk update status for multiple categories.
     *
     * @param  list<int>  $ids
     */
    public function bulkUpdateStatus(array $ids, bool $status, ?int $adminId = null): int
    {
        if (empty($ids)) {
            return 0;
        }

        $updatedCount = DB::transaction(function () use ($ids, $status, $adminId) {
            return CommodityCategory::query()
                ->whereIn('id', $ids)
                ->update([
                    'status' => $status,
                    'updated_by' => $adminId,
                ]);
        });

        $this->clearCache(null, $ids);

        return $updatedCount;
    }

    /**
     * Soft delete a single commodity category safely.
     *
     * @throws DomainException
     */
    public function deleteCategory(CommodityCategory $category): void
    {
        if (Commodity::query()->where('commodity_category_id', $category->id)->exists()) {
            throw new DomainException('Commodity category cannot be deleted because it has commodities assigned.');
        }

        $categoryId = (int) $category->id;

        DB::transaction(function () use ($category) {
            $category->delete();
        });

        $this->clearCache($categoryId);
    }

    /**
     * Bulk soft delete multiple categories atomically with dependency checking.
     *
     * @param  list<int>  $ids
     * @return array{deleted_count: int, blocked_ids: list<int>}
     */
    public function bulkDeleteCategories(array $ids): array
    {
        if (empty($ids)) {
            return ['deleted_count' => 0, 'blocked_ids' => []];
        }

        // Check if any category has assigned commodities
        $blockedIds = Commodity::query()
            ->whereIn('commodity_category_id', $ids)
            ->distinct()
            ->pluck('commodity_category_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        if (! empty($blockedIds)) {
            return [
                'deleted_count' => 0,
                'blocked_ids' => array_values($blockedIds),
            ];
        }

        $deletedCount = DB::transaction(function () use ($ids) {
            return CommodityCategory::query()
                ->whereIn('id', $ids)
                ->delete();
        });

        $this->clearCache(null, $ids);

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
            CommodityCategory::query()
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
     * Invalidate commodity category Redis caches, corresponding commodity options, subcategory options, and variety options.
     *
     * @param  int|null  $categoryId
     * @param  list<int>  $categoryIds
     */
    public function clearCache(?int $categoryId = null, array $categoryIds = []): void
    {
        try {
            Cache::forget(self::CACHE_KEY_OPTIONS);
            Cache::forget(self::CACHE_KEY_ACTIVE);

            // Invalidate commodity options cache
            Cache::forget(CommodityService::CACHE_KEY_OPTIONS_ALL);

            $allCatIds = array_filter(array_unique(array_merge(
                $categoryId !== null ? [$categoryId] : [],
                $categoryIds
            )));

            if (! empty($allCatIds)) {
                foreach ($allCatIds as $catId) {
                    Cache::forget(CommodityService::CACHE_KEY_OPTIONS_CATEGORY_PREFIX.$catId);
                }

                // Invalidate subcategories options caches for all affected commodities in ONE query
                $affectedCommodityIds = Commodity::query()
                    ->whereIn('commodity_category_id', $allCatIds)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->toArray();

                Cache::forget(CommoditySubcategoryService::CACHE_KEY_OPTIONS_ALL);
                foreach ($affectedCommodityIds as $commId) {
                    Cache::forget(CommoditySubcategoryService::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$commId);
                }

                // Cross-module cache invalidation for Commodity Varieties
                Cache::forget(CommodityVarietyService::CACHE_KEY_OPTIONS_ALL);
                foreach ($affectedCommodityIds as $commId) {
                    Cache::forget(CommodityVarietyService::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$commId);
                }

                $affectedSubcategoryIds = CommoditySubcategory::query()
                    ->whereIn('commodity_id', $affectedCommodityIds)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->toArray();

                foreach ($affectedSubcategoryIds as $subId) {
                    Cache::forget(CommodityVarietyService::CACHE_KEY_OPTIONS_SUBCATEGORY_PREFIX.$subId);
                }

                // Cross-module cache invalidation for Commodity Grades
                Cache::forget(CommodityGradeService::CACHE_KEY_OPTIONS_ALL);
                foreach ($affectedCommodityIds as $commId) {
                    Cache::forget(CommodityGradeService::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$commId);
                }
                foreach ($affectedSubcategoryIds as $subId) {
                    Cache::forget(CommodityGradeService::CACHE_KEY_OPTIONS_SUBCATEGORY_PREFIX.$subId);
                }
            } else {
                Cache::forget(CommoditySubcategoryService::CACHE_KEY_OPTIONS_ALL);
                Cache::forget(CommodityVarietyService::CACHE_KEY_OPTIONS_ALL);
                Cache::forget(CommodityGradeService::CACHE_KEY_OPTIONS_ALL);
            }
        } catch (\Throwable $e) {
            // Non-blocking cache exception
        }
    }
}
