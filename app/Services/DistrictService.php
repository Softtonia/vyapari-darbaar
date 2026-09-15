<?php

namespace App\Services;

use App\Models\District;
use App\Models\Mandi;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DistrictService
{
    /**
     * Cache key prefix for active districts dropdown options scoped by state.
     */
    public const CACHE_KEY_OPTIONS_STATE_PREFIX = 'locations:districts:options:state:';

    /**
     * Cache TTL in seconds (1 hour).
     */
    public const CACHE_TTL_SECONDS = 3600;

    /**
     * Get a paginated list of districts with dynamic filtering, relations, and sorting.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listDistricts(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = max(1, min(100, $perPage));

        $sortBy = (string) ($filters['sort_by'] ?? 'sort_order');
        $sortOrder = (string) ($filters['sort_order'] ?? 'asc');

        $query = District::query()
            ->select([
                'id',
                'state_id',
                'name',
                'slug',
                'code',
                'sort_order',
                'status',
                'created_by',
                'updated_by',
                'created_at',
                'updated_at',
            ])
            ->with(['state:id,name,slug,code'])
            ->state($filters['state_id'] ?? null)
            ->search($filters['search'] ?? null)
            ->status($filters['status'] ?? null)
            ->sorted($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get lightweight, cached list of active districts for dropdowns.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOptions(?int $stateId = null): array
    {
        if ($stateId !== null) {
            return Cache::remember(self::CACHE_KEY_OPTIONS_STATE_PREFIX.$stateId, self::CACHE_TTL_SECONDS, function () use ($stateId) {
                return District::query()
                    ->active()
                    ->where('state_id', $stateId)
                    ->select(['id', 'state_id', 'name', 'slug', 'code'])
                    ->orderBy('sort_order', 'asc')
                    ->orderBy('name', 'asc')
                    ->get()
                    ->toArray();
            });
        }

        return District::query()
            ->active()
            ->select(['id', 'state_id', 'name', 'slug', 'code'])
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * Create a new district.
     *
     * @param  array<string, mixed>  $data
     */
    public function createDistrict(array $data, ?int $adminId = null): District
    {
        $stateId = (int) $data['state_id'];

        $district = DB::transaction(function () use ($data, $stateId, $adminId) {
            $slug = ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['name']);
            $slug = $this->generateUniqueSlug($stateId, $slug);

            $code = ! empty($data['code']) ? strtoupper(trim((string) $data['code'])) : null;

            return District::create([
                'state_id' => $stateId,
                'name' => trim((string) $data['name']),
                'slug' => $slug,
                'code' => $code,
                'sort_order' => $data['sort_order'] ?? 0,
                'status' => $data['status'] ?? true,
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]);
        });

        $this->clearCache($stateId, [], (int) $district->id);

        return $district->fresh(['state:id,name,slug,code']);
    }

    /**
     * Update an existing district.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateDistrict(District $district, array $data, ?int $adminId = null): District
    {
        $oldStateId = (int) $district->state_id;
        $newStateId = array_key_exists('state_id', $data) ? (int) $data['state_id'] : $oldStateId;

        $updatedDistrict = DB::transaction(function () use ($district, $data, $oldStateId, $newStateId, $adminId) {
            $updateData = [];

            if (array_key_exists('state_id', $data)) {
                $updateData['state_id'] = $newStateId;
            }

            if (array_key_exists('name', $data)) {
                $updateData['name'] = trim((string) $data['name']);
            }

            if (array_key_exists('slug', $data) && ! empty($data['slug'])) {
                $updateData['slug'] = $this->generateUniqueSlug($newStateId, Str::slug($data['slug']), (int) $district->id);
            } elseif ($newStateId !== $oldStateId) {
                $updateData['slug'] = $this->generateUniqueSlug($newStateId, $district->slug, (int) $district->id);
            }

            if (array_key_exists('code', $data)) {
                $updateData['code'] = ! empty($data['code']) ? strtoupper(trim((string) $data['code'])) : null;
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

            $district->update($updateData);

            return $district->fresh(['state:id,name,slug,code', 'creator', 'updater']);
        });

        $affectedStateIds = array_unique(array_filter([$oldStateId, $newStateId]));
        $this->clearCache(null, $affectedStateIds, (int) $updatedDistrict->id);

        return $updatedDistrict;
    }

    /**
     * Update district active/inactive status.
     */
    public function updateStatus(District $district, bool $status, ?int $adminId = null): District
    {
        $updatedDistrict = DB::transaction(function () use ($district, $status, $adminId) {
            $district->update([
                'status' => $status,
                'updated_by' => $adminId,
            ]);

            return $district->fresh(['state:id,name,slug,code']);
        });

        $this->clearCache((int) $district->state_id, [], (int) $district->id);

        return $updatedDistrict;
    }

    /**
     * Bulk update status for multiple districts.
     *
     * @param  list<int>  $ids
     */
    public function bulkUpdateStatus(array $ids, bool $status, ?int $adminId = null): int
    {
        if (empty($ids)) {
            return 0;
        }

        $affectedStateIds = District::query()
            ->whereIn('id', $ids)
            ->distinct()
            ->pluck('state_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $updatedCount = DB::transaction(function () use ($ids, $status, $adminId) {
            return District::query()
                ->whereIn('id', $ids)
                ->update([
                    'status' => $status,
                    'updated_by' => $adminId,
                ]);
        });

        $this->clearCache(null, $affectedStateIds, null, $ids);

        return $updatedCount;
    }

    /**
     * Soft delete a single district safely.
     *
     * @throws DomainException
     */
    public function deleteDistrict(District $district): void
    {
        if (Mandi::query()->where('district_id', $district->id)->exists()) {
            throw new DomainException('District cannot be deleted because mandis are associated with it.');
        }

        $stateId = (int) $district->state_id;
        $districtId = (int) $district->id;

        DB::transaction(function () use ($district) {
            $district->delete();
        });

        $this->clearCache($stateId, [], $districtId);
    }

    /**
     * Bulk soft delete multiple districts atomically with dependency checking.
     *
     * @param  list<int>  $ids
     * @return array{deleted_count: int, blocked_ids: list<int>}
     */
    public function bulkDeleteDistricts(array $ids): array
    {
        if (empty($ids)) {
            return ['deleted_count' => 0, 'blocked_ids' => []];
        }

        // Check if any district has assigned mandis
        $blockedIds = Mandi::query()
            ->whereIn('district_id', $ids)
            ->distinct()
            ->pluck('district_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        if (! empty($blockedIds)) {
            return [
                'deleted_count' => 0,
                'blocked_ids' => array_values($blockedIds),
            ];
        }

        $affectedStateIds = District::query()
            ->whereIn('id', $ids)
            ->distinct()
            ->pluck('state_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $deletedCount = DB::transaction(function () use ($ids) {
            return District::query()
                ->whereIn('id', $ids)
                ->delete();
        });

        $this->clearCache(null, $affectedStateIds, null, $ids);

        return [
            'deleted_count' => $deletedCount,
            'blocked_ids' => [],
        ];
    }

    /**
     * Generate a deterministic unique slug ensuring uniqueness within the state (including soft deleted rows).
     */
    public function generateUniqueSlug(int $stateId, string $baseSlug, ?int $ignoreId = null): string
    {
        $slug = Str::slug($baseSlug);
        $originalSlug = $slug;
        $counter = 2;

        while (
            District::query()
                ->withTrashed()
                ->where('state_id', $stateId)
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
     * Invalidate district and child mandi option caches.
     *
     * @param  int|null  $stateId
     * @param  list<int>  $stateIds
     * @param  int|null  $districtId
     * @param  list<int>  $districtIds
     */
    public function clearCache(?int $stateId = null, array $stateIds = [], ?int $districtId = null, array $districtIds = []): void
    {
        try {
            $allStateIds = array_filter(array_unique(array_merge(
                $stateId !== null ? [$stateId] : [],
                $stateIds
            )));

            foreach ($allStateIds as $sId) {
                Cache::forget(self::CACHE_KEY_OPTIONS_STATE_PREFIX.$sId);
                Cache::forget(MandiService::CACHE_KEY_OPTIONS_STATE_PREFIX.$sId);
            }

            $allDistrictIds = array_filter(array_unique(array_merge(
                $districtId !== null ? [$districtId] : [],
                $districtIds
            )));

            foreach ($allDistrictIds as $dId) {
                Cache::forget(MandiService::CACHE_KEY_OPTIONS_DISTRICT_PREFIX.$dId);
            }
        } catch (\Throwable $e) {
            // Non-blocking cache exception
        }
    }
}
