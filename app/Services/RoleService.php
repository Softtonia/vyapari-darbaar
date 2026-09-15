<?php

namespace App\Services;

use App\Models\Role;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class RoleService
{
    /**
     * Get a paginated list of roles with dynamic filtering and sorting.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listRoles(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = max(1, min(100, $perPage));

        $sortBy = (string) ($filters['sort_by'] ?? 'id');
        $sortOrder = (string) ($filters['sort_order'] ?? 'desc');

        $query = Role::query()
            ->select([
                'id',
                'name',
                'guard_name',
                'slug',
                'status',
                'is_default',
                'created_at',
                'updated_at',
            ])
            ->search($filters['search'] ?? null)
            ->status($filters['status'] ?? null)
            ->isDefault($filters['is_default'] ?? null)
            ->sort($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Create a new custom role.
     *
     * @param  array{name: string, slug: string, guard_name?: string, status?: bool}  $data
     */
    public function createRole(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            $role = Role::create([
                'name' => $data['name'],
                'guard_name' => $data['guard_name'] ?? 'web',
                'slug' => $data['slug'],
                'status' => $data['status'] ?? true,
                'is_default' => false,
            ]);

            $this->clearRoleCache();

            return $role;
        });
    }

    /**
     * Update an existing role.
     *
     * @param  array{name: string, slug?: string, guard_name?: string, status?: bool}  $data
     */
    public function updateRole(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data) {
            $updateData = [
                'name' => $data['name'],
            ];

            if (isset($data['guard_name'])) {
                $updateData['guard_name'] = $data['guard_name'];
            }

            // Only update slug if it's not a protected default role
            if (! $role->is_default && isset($data['slug'])) {
                $updateData['slug'] = $data['slug'];
            }

            if (array_key_exists('status', $data)) {
                $updateData['status'] = (bool) $data['status'];
            }

            $role->update($updateData);

            $this->clearRoleCache();

            return $role->fresh();
        });
    }

    /**
     * Update the active status of a role.
     */
    public function updateStatus(Role $role, bool $status): Role
    {
        $role->update([
            'status' => $status,
        ]);

        $this->clearRoleCache();

        return $role->fresh();
    }

    /**
     * Soft delete a single non-system role.
     *
     * @throws DomainException
     */
    public function deleteRole(Role $role): void
    {
        if ($role->is_default) {
            throw new DomainException('System role cannot be deleted.');
        }

        DB::transaction(function () use ($role) {
            $role->delete();
            $this->clearRoleCache();
        });
    }

    /**
     * Bulk soft delete multiple roles safely in a single transaction.
     *
     * @param  list<int>  $ids
     * @return int Number of deleted roles
     *
     * @throws DomainException
     */
    public function bulkDeleteRoles(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        // Atomic check: Ensure NO protected default roles are within the requested deletion set
        $hasDefaultRoles = Role::query()
            ->whereIn('id', $ids)
            ->where('is_default', true)
            ->exists();

        if ($hasDefaultRoles) {
            throw new DomainException('System roles cannot be deleted.');
        }

        return DB::transaction(function () use ($ids) {
            $deletedCount = Role::query()
                ->whereIn('id', $ids)
                ->delete();

            $this->clearRoleCache();

            return $deletedCount;
        });
    }

    /**
     * Invalidate Spatie permissions and role cache across the system.
     */
    public function clearRoleCache(): void
    {
        try {
            app()[PermissionRegistrar::class]->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // Ignore if Spatie registrar is not bound in tests/console
        }
    }
}
