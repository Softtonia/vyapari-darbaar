<?php

namespace App\Services;

use App\Enums\MarketType;
use App\Models\District;
use App\Models\Mandi;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MandiService
{
    /**
     * Cache key prefix for active mandis dropdown options scoped by district.
     */
    public const CACHE_KEY_OPTIONS_DISTRICT_PREFIX = 'locations:mandis:options:district:';

    /**
     * Cache key prefix for active mandis dropdown options scoped by state.
     */
    public const CACHE_KEY_OPTIONS_STATE_PREFIX = 'locations:mandis:options:state:';

    /**
     * Cache TTL in seconds (1 hour).
     */
    public const CACHE_TTL_SECONDS = 3600;

    /**
     * Get a paginated list of mandis with dynamic filtering, relations, and sorting.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listMandis(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = max(1, min(100, $perPage));

        $sortBy = (string) ($filters['sort_by'] ?? 'sort_order');
        $sortOrder = (string) ($filters['sort_order'] ?? 'asc');

        $query = Mandi::query()
            ->select([
                'id',
                'district_id',
                'name',
                'slug',
                'code',
                'market_type',
                'address',
                'pincode',
                'latitude',
                'longitude',
                'contact_phone',
                'contact_email',
                'website',
                'sort_order',
                'status',
                'created_by',
                'updated_by',
                'created_at',
                'updated_at',
            ])
            ->with([
                'district:id,state_id,name,slug',
                'district.state:id,name,slug,code',
            ])
            ->state($filters['state_id'] ?? null)
            ->district($filters['district_id'] ?? null)
            ->marketType($filters['market_type'] ?? null)
            ->pincode($filters['pincode'] ?? null)
            ->search($filters['search'] ?? null)
            ->status($filters['status'] ?? null)
            ->sorted($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get lightweight, cached list of active mandis for dropdowns.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws DomainException
     */
    public function getOptions(?int $districtId = null, ?int $stateId = null): array
    {
        if ($districtId !== null && $stateId !== null) {
            $belongsToState = District::query()
                ->where('id', $districtId)
                ->where('state_id', $stateId)
                ->exists();

            if (! $belongsToState) {
                throw new DomainException('The specified district does not belong to the selected state.');
            }
        }

        if ($districtId !== null) {
            return Cache::remember(self::CACHE_KEY_OPTIONS_DISTRICT_PREFIX.$districtId, self::CACHE_TTL_SECONDS, function () use ($districtId) {
                return Mandi::query()
                    ->active()
                    ->where('district_id', $districtId)
                    ->select(['id', 'district_id', 'name', 'slug', 'code', 'market_type'])
                    ->orderBy('sort_order', 'asc')
                    ->orderBy('name', 'asc')
                    ->get()
                    ->toArray();
            });
        }

        if ($stateId !== null) {
            return Cache::remember(self::CACHE_KEY_OPTIONS_STATE_PREFIX.$stateId, self::CACHE_TTL_SECONDS, function () use ($stateId) {
                return Mandi::query()
                    ->active()
                    ->state($stateId)
                    ->select(['mandis.id', 'mandis.district_id', 'mandis.name', 'mandis.slug', 'mandis.code', 'mandis.market_type'])
                    ->orderBy('mandis.sort_order', 'asc')
                    ->orderBy('mandis.name', 'asc')
                    ->get()
                    ->toArray();
            });
        }

        return Mandi::query()
            ->active()
            ->select(['id', 'district_id', 'name', 'slug', 'code', 'market_type'])
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * Create a new mandi.
     *
     * @param  array<string, mixed>  $data
     */
    public function createMandi(array $data, ?int $adminId = null): Mandi
    {
        $districtId = (int) $data['district_id'];
        $district = District::findOrFail($districtId);

        $mandi = DB::transaction(function () use ($data, $districtId, $adminId) {
            $slug = ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['name']);
            $slug = $this->generateUniqueSlug($districtId, $slug);

            $code = strtoupper(trim((string) $data['code']));

            $marketType = $data['market_type'] ?? MarketType::APMC->value;
            if ($marketType instanceof MarketType) {
                $marketType = $marketType->value;
            }

            return Mandi::create([
                'district_id' => $districtId,
                'name' => trim((string) $data['name']),
                'slug' => $slug,
                'code' => $code,
                'market_type' => $marketType,
                'address' => $data['address'] ?? null,
                'pincode' => ! empty($data['pincode']) ? trim((string) $data['pincode']) : null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'website' => $data['website'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
                'status' => $data['status'] ?? true,
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]);
        });

        $this->clearCache($districtId, [], (int) $district->state_id);

        return $mandi->fresh([
            'district:id,state_id,name,slug',
            'district.state:id,name,slug,code',
        ]);
    }

    /**
     * Update an existing mandi.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateMandi(Mandi $mandi, array $data, ?int $adminId = null): Mandi
    {
        $oldDistrictId = (int) $mandi->district_id;
        $oldStateId = (int) ($mandi->district?->state_id ?? District::where('id', $oldDistrictId)->value('state_id'));

        $newDistrictId = array_key_exists('district_id', $data) ? (int) $data['district_id'] : $oldDistrictId;
        $newStateId = $newDistrictId !== $oldDistrictId
            ? (int) District::where('id', $newDistrictId)->value('state_id')
            : $oldStateId;

        $updatedMandi = DB::transaction(function () use ($mandi, $data, $oldDistrictId, $newDistrictId, $adminId) {
            $updateData = [];

            if (array_key_exists('district_id', $data)) {
                $updateData['district_id'] = $newDistrictId;
            }

            if (array_key_exists('name', $data)) {
                $updateData['name'] = trim((string) $data['name']);
            }

            if (array_key_exists('slug', $data) && ! empty($data['slug'])) {
                $updateData['slug'] = $this->generateUniqueSlug($newDistrictId, Str::slug($data['slug']), (int) $mandi->id);
            } elseif ($newDistrictId !== $oldDistrictId) {
                $updateData['slug'] = $this->generateUniqueSlug($newDistrictId, $mandi->slug, (int) $mandi->id);
            }

            if (array_key_exists('code', $data)) {
                $updateData['code'] = strtoupper(trim((string) $data['code']));
            }

            if (array_key_exists('market_type', $data)) {
                $marketType = $data['market_type'];
                $updateData['market_type'] = $marketType instanceof MarketType ? $marketType->value : $marketType;
            }

            if (array_key_exists('address', $data)) {
                $updateData['address'] = $data['address'];
            }

            if (array_key_exists('pincode', $data)) {
                $updateData['pincode'] = ! empty($data['pincode']) ? trim((string) $data['pincode']) : null;
            }

            if (array_key_exists('latitude', $data)) {
                $updateData['latitude'] = $data['latitude'];
            }

            if (array_key_exists('longitude', $data)) {
                $updateData['longitude'] = $data['longitude'];
            }

            if (array_key_exists('contact_phone', $data)) {
                $updateData['contact_phone'] = $data['contact_phone'];
            }

            if (array_key_exists('contact_email', $data)) {
                $updateData['contact_email'] = $data['contact_email'];
            }

            if (array_key_exists('website', $data)) {
                $updateData['website'] = $data['website'];
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

            $mandi->update($updateData);

            return $mandi->fresh([
                'district:id,state_id,name,slug',
                'district.state:id,name,slug,code',
                'creator',
                'updater',
            ]);
        });

        $affectedDistrictIds = array_unique(array_filter([$oldDistrictId, $newDistrictId]));
        $affectedStateIds = array_unique(array_filter([$oldStateId, $newStateId]));

        $this->clearCache(null, $affectedDistrictIds, null, $affectedStateIds);

        return $updatedMandi;
    }

    /**
     * Update mandi active/inactive status.
     */
    public function updateStatus(Mandi $mandi, bool $status, ?int $adminId = null): Mandi
    {
        $updatedMandi = DB::transaction(function () use ($mandi, $status, $adminId) {
            $mandi->update([
                'status' => $status,
                'updated_by' => $adminId,
            ]);

            return $mandi->fresh([
                'district:id,state_id,name,slug',
                'district.state:id,name,slug,code',
            ]);
        });

        $stateId = (int) ($mandi->district?->state_id ?? District::where('id', $mandi->district_id)->value('state_id'));
        $this->clearCache((int) $mandi->district_id, [], $stateId);

        return $updatedMandi;
    }

    /**
     * Bulk update status for multiple mandis.
     *
     * @param  list<int>  $ids
     */
    public function bulkUpdateStatus(array $ids, bool $status, ?int $adminId = null): int
    {
        if (empty($ids)) {
            return 0;
        }

        $affectedDistrictIds = Mandi::query()
            ->whereIn('id', $ids)
            ->distinct()
            ->pluck('district_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $affectedStateIds = District::query()
            ->whereIn('id', $affectedDistrictIds)
            ->distinct()
            ->pluck('state_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $updatedCount = DB::transaction(function () use ($ids, $status, $adminId) {
            return Mandi::query()
                ->whereIn('id', $ids)
                ->update([
                    'status' => $status,
                    'updated_by' => $adminId,
                ]);
        });

        $this->clearCache(null, $affectedDistrictIds, null, $affectedStateIds);

        return $updatedCount;
    }

    /**
     * Soft delete a single mandi safely.
     */
    public function deleteMandi(Mandi $mandi): void
    {
        $districtId = (int) $mandi->district_id;
        $stateId = (int) ($mandi->district?->state_id ?? District::where('id', $districtId)->value('state_id'));

        DB::transaction(function () use ($mandi) {
            $mandi->delete();
        });

        $this->clearCache($districtId, [], $stateId);
    }

    /**
     * Bulk soft delete multiple mandis atomically.
     *
     * @param  list<int>  $ids
     * @return array{deleted_count: int, blocked_ids: list<int>}
     */
    public function bulkDeleteMandis(array $ids): array
    {
        if (empty($ids)) {
            return ['deleted_count' => 0, 'blocked_ids' => []];
        }

        $affectedDistrictIds = Mandi::query()
            ->whereIn('id', $ids)
            ->distinct()
            ->pluck('district_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $affectedStateIds = District::query()
            ->whereIn('id', $affectedDistrictIds)
            ->distinct()
            ->pluck('state_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $deletedCount = DB::transaction(function () use ($ids) {
            return Mandi::query()
                ->whereIn('id', $ids)
                ->delete();
        });

        $this->clearCache(null, $affectedDistrictIds, null, $affectedStateIds);

        return [
            'deleted_count' => $deletedCount,
            'blocked_ids' => [],
        ];
    }

    /**
     * Generate a deterministic unique slug ensuring uniqueness within the district (including soft deleted rows).
     */
    public function generateUniqueSlug(int $districtId, string $baseSlug, ?int $ignoreId = null): string
    {
        $slug = Str::slug($baseSlug);
        $originalSlug = $slug;
        $counter = 2;

        while (
            Mandi::query()
                ->withTrashed()
                ->where('district_id', $districtId)
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
     * Invalidate mandi dropdown option caches scoped by district and state.
     *
     * @param  int|null  $districtId
     * @param  list<int>  $districtIds
     * @param  int|null  $stateId
     * @param  list<int>  $stateIds
     */
    public function clearCache(?int $districtId = null, array $districtIds = [], ?int $stateId = null, array $stateIds = []): void
    {
        try {
            $allDistrictIds = array_filter(array_unique(array_merge(
                $districtId !== null ? [$districtId] : [],
                $districtIds
            )));

            foreach ($allDistrictIds as $dId) {
                Cache::forget(self::CACHE_KEY_OPTIONS_DISTRICT_PREFIX.$dId);
            }

            $allStateIds = array_filter(array_unique(array_merge(
                $stateId !== null ? [$stateId] : [],
                $stateIds
            )));

            foreach ($allStateIds as $sId) {
                Cache::forget(self::CACHE_KEY_OPTIONS_STATE_PREFIX.$sId);
            }
        } catch (\Throwable $e) {
            // Non-blocking cache exception
        }
    }
}
