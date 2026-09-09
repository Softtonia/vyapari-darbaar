<?php

namespace App\Services;

use App\Models\CommodityGrade;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommodityGradeService
{
    /**
     * Cache key prefix for options.
     */
    public const CACHE_KEY_OPTIONS_ALL = 'commodity_grades:options:all';

    public const CACHE_KEY_OPTIONS_COMMODITY_PREFIX = 'commodity_grades:options:commodity:';

    public const CACHE_KEY_OPTIONS_SUBCATEGORY_PREFIX = 'commodity_grades:options:subcategory:';

    public const CACHE_KEY_OPTIONS_VARIETY_PREFIX = 'commodity_grades:options:variety:';

    /**
     * Cache TTL in seconds (1 hour).
     */
    public const CACHE_TTL_SECONDS = 3600;

    /**
     * Get a paginated list of commodity grades with dynamic filtering and sorting.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listCommodityGrades(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = max(1, min(100, $perPage));

        $sortBy = (string) ($filters['sort_by'] ?? 'sort_order');
        $sortOrder = (string) ($filters['sort_order'] ?? 'asc');

        $query = CommodityGrade::query()
            ->with([
                'commodity:id,commodity_category_id,name_en,name_hi,slug',
                'commodity.category:id,name_en,name_hi,slug',
                'subcategory:id,commodity_id,name_en,name_hi,slug',
                'variety:id,commodity_id,commodity_subcategory_id,name_en,name_hi,slug',
            ])
            ->select([
                'id',
                'commodity_id',
                'commodity_subcategory_id',
                'commodity_variety_id',
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
            ->subcategory($filters['commodity_subcategory_id'] ?? null)
            ->variety($filters['commodity_variety_id'] ?? null)
            ->category($filters['commodity_category_id'] ?? null)
            ->status($filters['status'] ?? null)
            ->sorted($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get lightweight, cached list of active commodity grades for dropdown options.
     * Requires grade, commodity, and parent category to all be active and non-deleted.
     * If assigned to a subcategory or variety, they must also be active and non-deleted.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOptions(?int $commodityId = null, ?int $subcategoryId = null, ?int $varietyId = null): array
    {
        if ($varietyId !== null) {
            $cacheKey = self::CACHE_KEY_OPTIONS_VARIETY_PREFIX.$varietyId;
        } elseif ($subcategoryId !== null) {
            $cacheKey = self::CACHE_KEY_OPTIONS_SUBCATEGORY_PREFIX.$subcategoryId;
        } elseif ($commodityId !== null) {
            $cacheKey = self::CACHE_KEY_OPTIONS_COMMODITY_PREFIX.$commodityId;
        } else {
            $cacheKey = self::CACHE_KEY_OPTIONS_ALL;
        }

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($commodityId, $subcategoryId, $varietyId) {
            return CommodityGrade::query()
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
                ->where(function (Builder $query) {
                    $query->whereNull('commodity_variety_id')
                        ->orWhereHas('variety', function (Builder $q) {
                            $q->active();
                        });
                })
                ->when($commodityId !== null, fn (Builder $q) => $q->where('commodity_id', $commodityId))
                ->when($subcategoryId !== null, fn (Builder $q) => $q->where('commodity_subcategory_id', $subcategoryId))
                ->when($varietyId !== null, fn (Builder $q) => $q->where('commodity_variety_id', $varietyId))
                ->select(['id', 'commodity_id', 'commodity_subcategory_id', 'commodity_variety_id', 'name_en', 'name_hi', 'slug'])
                ->orderBy('sort_order', 'asc')
                ->orderBy('id', 'asc')
                ->get()
                ->toArray();
        });
    }

    /**
     * Create a new commodity grade.
     *
     * @param  array<string, mixed>  $data
     */
    public function createCommodityGrade(array $data, ?int $adminId = null): CommodityGrade
    {
        $commodityId = (int) $data['commodity_id'];
        $subcategoryId = ! empty($data['commodity_subcategory_id']) ? (int) $data['commodity_subcategory_id'] : null;
        $varietyId = ! empty($data['commodity_variety_id']) ? (int) $data['commodity_variety_id'] : null;

        $grade = DB::transaction(function () use ($data, $commodityId, $subcategoryId, $varietyId, $adminId) {
            $slug = ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['name_en']);
            $slug = $this->generateUniqueSlug($slug, $commodityId);

            return CommodityGrade::create([
                'commodity_id' => $commodityId,
                'commodity_subcategory_id' => $subcategoryId,
                'commodity_variety_id' => $varietyId,
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

        $this->clearCache($commodityId, [], $subcategoryId, [], $varietyId, []);

        return $grade->load([
            'commodity:id,commodity_category_id,name_en,name_hi,slug',
            'commodity.category:id,name_en,name_hi,slug',
            'subcategory:id,commodity_id,name_en,name_hi,slug',
            'variety:id,commodity_id,commodity_subcategory_id,name_en,name_hi,slug',
        ]);
    }

    /**
     * Update an existing commodity grade.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateCommodityGrade(CommodityGrade $commodityGrade, array $data, ?int $adminId = null): CommodityGrade
    {
        $oldCommodityId = (int) $commodityGrade->commodity_id;
        $oldSubcategoryId = $commodityGrade->commodity_subcategory_id ? (int) $commodityGrade->commodity_subcategory_id : null;
        $oldVarietyId = $commodityGrade->commodity_variety_id ? (int) $commodityGrade->commodity_variety_id : null;

        $updatedGrade = DB::transaction(function () use ($commodityGrade, $data, $adminId) {
            $updateData = [];

            if (array_key_exists('commodity_id', $data)) {
                $updateData['commodity_id'] = (int) $data['commodity_id'];
            }

            if (array_key_exists('commodity_subcategory_id', $data)) {
                $updateData['commodity_subcategory_id'] = $data['commodity_subcategory_id'] !== null ? (int) $data['commodity_subcategory_id'] : null;
            }

            if (array_key_exists('commodity_variety_id', $data)) {
                $updateData['commodity_variety_id'] = $data['commodity_variety_id'] !== null ? (int) $data['commodity_variety_id'] : null;
            }

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

            $commodityGrade->update($updateData);

            return $commodityGrade->fresh([
                'commodity:id,commodity_category_id,name_en,name_hi,slug',
                'commodity.category:id,name_en,name_hi,slug',
                'subcategory:id,commodity_id,name_en,name_hi,slug',
                'variety:id,commodity_id,commodity_subcategory_id,name_en,name_hi,slug',
                'creator',
                'updater',
            ]);
        });

        $newCommodityId = (int) $updatedGrade->commodity_id;
        $newSubcategoryId = $updatedGrade->commodity_subcategory_id ? (int) $updatedGrade->commodity_subcategory_id : null;
        $newVarietyId = $updatedGrade->commodity_variety_id ? (int) $updatedGrade->commodity_variety_id : null;

        $affectedCommodityIds = array_values(array_filter(array_unique([$oldCommodityId, $newCommodityId])));
        $affectedSubcategoryIds = array_values(array_filter(array_unique([$oldSubcategoryId, $newSubcategoryId])));
        $affectedVarietyIds = array_values(array_filter(array_unique([$oldVarietyId, $newVarietyId])));

        $this->clearCache(null, $affectedCommodityIds, null, $affectedSubcategoryIds, null, $affectedVarietyIds);

        return $updatedGrade;
    }

    /**
     * Update commodity grade active/inactive status.
     */
    public function updateStatus(CommodityGrade $commodityGrade, bool $status, ?int $adminId = null): CommodityGrade
    {
        $commodityId = (int) $commodityGrade->commodity_id;
        $subcategoryId = $commodityGrade->commodity_subcategory_id ? (int) $commodityGrade->commodity_subcategory_id : null;
        $varietyId = $commodityGrade->commodity_variety_id ? (int) $commodityGrade->commodity_variety_id : null;

        $updatedGrade = DB::transaction(function () use ($commodityGrade, $status, $adminId) {
            $commodityGrade->update([
                'status' => $status,
                'updated_by' => $adminId,
            ]);

            return $commodityGrade->fresh([
                'commodity:id,commodity_category_id,name_en,name_hi,slug',
                'commodity.category:id,name_en,name_hi,slug',
                'subcategory:id,commodity_id,name_en,name_hi,slug',
                'variety:id,commodity_id,commodity_subcategory_id,name_en,name_hi,slug',
            ]);
        });

        $this->clearCache($commodityId, [], $subcategoryId, [], $varietyId, []);

        return $updatedGrade;
    }

    /**
     * Bulk update status for multiple commodity grades.
     *
     * @param  list<int>  $ids
     */
    public function bulkUpdateStatus(array $ids, bool $status, ?int $adminId = null): int
    {
        if (empty($ids)) {
            return 0;
        }

        $grades = CommodityGrade::query()
            ->whereIn('id', $ids)
            ->get(['id', 'commodity_id', 'commodity_subcategory_id', 'commodity_variety_id']);

        $foundIds = $grades->pluck('id')->all();
        $missingIds = array_diff($ids, $foundIds);

        if (! empty($missingIds)) {
            throw ValidationException::withMessages([
                'ids' => ['One or more selected commodity grade IDs are invalid or already deleted.'],
            ]);
        }

        $affectedCommodityIds = $grades->pluck('commodity_id')->filter()->unique()->map(fn ($id) => (int) $id)->values()->toArray();
        $affectedSubcategoryIds = $grades->pluck('commodity_subcategory_id')->filter()->unique()->map(fn ($id) => (int) $id)->values()->toArray();
        $affectedVarietyIds = $grades->pluck('commodity_variety_id')->filter()->unique()->map(fn ($id) => (int) $id)->values()->toArray();

        $updatedCount = DB::transaction(function () use ($ids, $status, $adminId) {
            return CommodityGrade::query()
                ->whereIn('id', $ids)
                ->update([
                    'status' => $status,
                    'updated_by' => $adminId,
                ]);
        });

        $this->clearCache(null, $affectedCommodityIds, null, $affectedSubcategoryIds, null, $affectedVarietyIds);

        return $updatedCount;
    }

    /**
     * Soft delete a single commodity grade safely.
     */
    public function deleteCommodityGrade(CommodityGrade $commodityGrade): void
    {
        $commodityId = (int) $commodityGrade->commodity_id;
        $subcategoryId = $commodityGrade->commodity_subcategory_id ? (int) $commodityGrade->commodity_subcategory_id : null;
        $varietyId = $commodityGrade->commodity_variety_id ? (int) $commodityGrade->commodity_variety_id : null;

        DB::transaction(function () use ($commodityGrade) {
            $commodityGrade->delete();
        });

        $this->clearCache($commodityId, [], $subcategoryId, [], $varietyId, []);
    }

    /**
     * Bulk soft delete multiple commodity grades atomically in a single transaction.
     *
     * @param  list<int>  $ids
     * @return int Number of deleted grades
     */
    public function bulkDeleteCommodityGrades(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $grades = CommodityGrade::query()
            ->whereIn('id', $ids)
            ->get(['id', 'commodity_id', 'commodity_subcategory_id', 'commodity_variety_id']);

        $foundIds = $grades->pluck('id')->all();
        $missingIds = array_diff($ids, $foundIds);

        if (! empty($missingIds)) {
            throw ValidationException::withMessages([
                'ids' => ['One or more selected commodity grade IDs are invalid or already deleted.'],
            ]);
        }

        $affectedCommodityIds = $grades->pluck('commodity_id')->filter()->unique()->map(fn ($id) => (int) $id)->values()->toArray();
        $affectedSubcategoryIds = $grades->pluck('commodity_subcategory_id')->filter()->unique()->map(fn ($id) => (int) $id)->values()->toArray();
        $affectedVarietyIds = $grades->pluck('commodity_variety_id')->filter()->unique()->map(fn ($id) => (int) $id)->values()->toArray();

        $deletedCount = DB::transaction(function () use ($ids) {
            return CommodityGrade::query()
                ->whereIn('id', $ids)
                ->delete();
        });

        $this->clearCache(null, $affectedCommodityIds, null, $affectedSubcategoryIds, null, $affectedVarietyIds);

        return $deletedCount;
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
            CommodityGrade::query()
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
     * Invalidate commodity grade options caches.
     */
    public function clearCache(
        ?int $commodityId = null,
        array $commodityIds = [],
        ?int $subcategoryId = null,
        array $subcategoryIds = [],
        ?int $varietyId = null,
        array $varietyIds = []
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

            if ($varietyId !== null) {
                Cache::forget(self::CACHE_KEY_OPTIONS_VARIETY_PREFIX.$varietyId);
            }

            foreach ($varietyIds as $vId) {
                if ($vId) {
                    Cache::forget(self::CACHE_KEY_OPTIONS_VARIETY_PREFIX.$vId);
                }
            }
        } catch (\Throwable $e) {
            // Non-blocking cache exception
        }
    }
}
