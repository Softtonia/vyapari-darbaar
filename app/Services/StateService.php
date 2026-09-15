<?php

namespace App\Services;

use App\Models\District;
use App\Models\State;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StateService
{
    /**
     * Cache key for active states dropdown options.
     */
    public const CACHE_KEY_OPTIONS = 'locations:states:options';

    /**
     * Cache TTL in seconds (1 hour).
     */
    public const CACHE_TTL_SECONDS = 3600;

    /**
     * Get a paginated list of states with dynamic filtering and sorting.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listStates(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = max(1, min(100, $perPage));

        $sortBy = (string) ($filters['sort_by'] ?? 'sort_order');
        $sortOrder = (string) ($filters['sort_order'] ?? 'asc');

        $query = State::query()
            ->select([
                'id',
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
            ->search($filters['search'] ?? null)
            ->status($filters['status'] ?? null)
            ->sorted($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get lightweight, cached list of active states for dropdowns.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOptions(): array
    {
        return Cache::remember(self::CACHE_KEY_OPTIONS, self::CACHE_TTL_SECONDS, function () {
            return State::query()
                ->active()
                ->select(['id', 'name', 'slug', 'code'])
                ->orderBy('sort_order', 'asc')
                ->orderBy('name', 'asc')
                ->get()
                ->toArray();
        });
    }

    /**
     * Create a new state.
     *
     * @param  array<string, mixed>  $data
     */
    public function createState(array $data, ?int $adminId = null): State
    {
        $state = DB::transaction(function () use ($data, $adminId) {
            $slug = ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['name']);
            $slug = $this->generateUniqueSlug($slug);

            $code = strtoupper(trim((string) ($data['code'] ?? '')));

            return State::create([
                'name' => trim((string) $data['name']),
                'slug' => $slug,
                'code' => $code,
                'sort_order' => $data['sort_order'] ?? 0,
                'status' => $data['status'] ?? true,
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]);
        });

        $this->clearCache((int) $state->id);

        return $state;
    }

    /**
     * Update an existing state.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateState(State $state, array $data, ?int $adminId = null): State
    {
        $updatedState = DB::transaction(function () use ($state, $data, $adminId) {
            $updateData = [];

            if (array_key_exists('name', $data)) {
                $updateData['name'] = trim((string) $data['name']);
            }

            if (array_key_exists('slug', $data) && ! empty($data['slug'])) {
                $updateData['slug'] = $this->generateUniqueSlug(Str::slug($data['slug']), (int) $state->id);
            }

            if (array_key_exists('code', $data)) {
                $updateData['code'] = strtoupper(trim((string) $data['code']));
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

            $state->update($updateData);

            return $state->fresh(['creator', 'updater']);
        });

        $this->clearCache((int) $updatedState->id);

        return $updatedState;
    }

    /**
     * Update state active/inactive status.
     */
    public function updateStatus(State $state, bool $status, ?int $adminId = null): State
    {
        $updatedState = DB::transaction(function () use ($state, $status, $adminId) {
            $state->update([
                'status' => $status,
                'updated_by' => $adminId,
            ]);

            return $state->fresh();
        });

        $this->clearCache((int) $updatedState->id);

        return $updatedState;
    }

    /**
     * Bulk update status for multiple states.
     *
     * @param  list<int>  $ids
     */
    public function bulkUpdateStatus(array $ids, bool $status, ?int $adminId = null): int
    {
        if (empty($ids)) {
            return 0;
        }

        $updatedCount = DB::transaction(function () use ($ids, $status, $adminId) {
            return State::query()
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
     * Soft delete a single state safely.
     *
     * @throws DomainException
     */
    public function deleteState(State $state): void
    {
        if (District::query()->where('state_id', $state->id)->exists()) {
            throw new DomainException('State cannot be deleted because districts are associated with it.');
        }

        $stateId = (int) $state->id;

        DB::transaction(function () use ($state) {
            $state->delete();
        });

        $this->clearCache($stateId);
    }

    /**
     * Bulk soft delete multiple states atomically with dependency checking.
     *
     * @param  list<int>  $ids
     * @return array{deleted_count: int, blocked_ids: list<int>}
     */
    public function bulkDeleteStates(array $ids): array
    {
        if (empty($ids)) {
            return ['deleted_count' => 0, 'blocked_ids' => []];
        }

        // Check if any state has assigned districts
        $blockedIds = District::query()
            ->whereIn('state_id', $ids)
            ->distinct()
            ->pluck('state_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        if (! empty($blockedIds)) {
            return [
                'deleted_count' => 0,
                'blocked_ids' => array_values($blockedIds),
            ];
        }

        $deletedCount = DB::transaction(function () use ($ids) {
            return State::query()
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
            State::query()
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
     * Invalidate state Redis caches and descendant district & mandi option caches.
     *
     * @param  int|null  $stateId
     * @param  list<int>  $stateIds
     */
    public function clearCache(?int $stateId = null, array $stateIds = []): void
    {
        try {
            Cache::forget(self::CACHE_KEY_OPTIONS);

            $allStateIds = array_filter(array_unique(array_merge(
                $stateId !== null ? [$stateId] : [],
                $stateIds
            )));

            if (! empty($allStateIds)) {
                foreach ($allStateIds as $sId) {
                    Cache::forget(DistrictService::CACHE_KEY_OPTIONS_STATE_PREFIX.$sId);
                    Cache::forget(MandiService::CACHE_KEY_OPTIONS_STATE_PREFIX.$sId);
                }

                $affectedDistrictIds = District::query()
                    ->whereIn('state_id', $allStateIds)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->toArray();

                foreach ($affectedDistrictIds as $dId) {
                    Cache::forget(MandiService::CACHE_KEY_OPTIONS_DISTRICT_PREFIX.$dId);
                }
            }
        } catch (\Throwable $e) {
            // Non-blocking cache exception
        }
    }
}
