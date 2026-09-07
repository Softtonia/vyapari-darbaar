<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Role\BulkDeleteRoleRequest;
use App\Http\Requests\Admin\Role\StoreRoleRequest;
use App\Http\Requests\Admin\Role\UpdateRoleRequest;
use App\Http\Requests\Admin\Role\UpdateRoleStatusRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\RoleService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * Display a paginated listing of roles.
     */
    public function index(Request $request, RoleService $roleService): JsonResponse
    {
        $paginator = $roleService->listRoles($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Roles retrieved successfully.',
            'data' => [
                'current_page' => $paginator->currentPage(),
                'data' => RoleResource::collection($paginator->items()),
                'first_page_url' => $paginator->url(1),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'last_page_url' => $paginator->url($paginator->lastPage()),
                'links' => $paginator->linkCollection()->toArray(),
                'next_page_url' => $paginator->nextPageUrl(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'prev_page_url' => $paginator->previousPageUrl(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ], 200);
    }

    /**
     * Store a newly created role.
     */
    public function store(StoreRoleRequest $request, RoleService $roleService): JsonResponse
    {
        $role = $roleService->createRole($request->validatedRoleData());

        return response()->json([
            'status' => true,
            'message' => 'Role created successfully.',
            'data' => new RoleResource($role),
        ], 201);
    }

    /**
     * Display the specified role.
     */
    public function show(Role $role): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Role retrieved successfully.',
            'data' => new RoleResource($role),
        ], 200);
    }

    /**
     * Update the specified role.
     */
    public function update(UpdateRoleRequest $request, Role $role, RoleService $roleService): JsonResponse
    {
        $updatedRole = $roleService->updateRole($role, $request->validatedRoleData($role));

        return response()->json([
            'status' => true,
            'message' => 'Role updated successfully.',
            'data' => new RoleResource($updatedRole),
        ], 200);
    }

    /**
     * Update the status of the specified role.
     */
    public function updateStatus(UpdateRoleStatusRequest $request, Role $role, RoleService $roleService): JsonResponse
    {
        $updatedRole = $roleService->updateStatus($role, $request->boolean('status'));

        return response()->json([
            'status' => true,
            'message' => 'Role status updated successfully.',
            'data' => new RoleResource($updatedRole),
        ], 200);
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Role $role, RoleService $roleService): JsonResponse
    {
        try {
            $roleService->deleteRole($role);

            return response()->json([
                'status' => true,
                'message' => 'Role deleted successfully.',
            ], 200);
        } catch (DomainException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Bulk delete multiple roles.
     */
    public function bulkDestroy(BulkDeleteRoleRequest $request, RoleService $roleService): JsonResponse
    {
        try {
            $deletedCount = $roleService->bulkDeleteRoles($request->validatedIds());

            return response()->json([
                'status' => true,
                'message' => 'Roles deleted successfully.',
                'data' => [
                    'deleted_count' => $deletedCount,
                ],
            ], 200);
        } catch (DomainException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }
}
