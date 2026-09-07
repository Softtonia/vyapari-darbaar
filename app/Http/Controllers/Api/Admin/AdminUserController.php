<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\User\BulkDeleteUsersAction;
use App\Actions\Admin\User\CreateUserAction;
use App\Actions\Admin\User\DeleteUserAction;
use App\Actions\Admin\User\ResendUserCredentialsAction;
use App\Actions\Admin\User\UpdateUserAction;
use App\Actions\Admin\User\UpdateUserStatusAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\BulkDeleteUsersRequest;
use App\Http\Requests\Admin\User\StoreUserRequest;
use App\Http\Requests\Admin\User\UpdateUserRequest;
use App\Http\Requests\Admin\User\UpdateUserStatusRequest;
use App\Http\Resources\AdminUserListResource;
use App\Http\Resources\AdminUserResource;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    /**
     * Allowed columns for sorting the user listing.
     *
     * @var list<string>
     */
    protected const ALLOWED_SORT_FIELDS = [
        'created_at',
        'first_name',
        'last_name',
        'name',
        'username',
        'email',
        'phone_number',
        'status',
    ];

    /**
     * Display a paginated listing of users.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min(100, $perPage));

        $query = User::query()
            ->with('roles')
            ->select([
                'id',
                'first_name',
                'last_name',
                'phone_number',
                'name',
                'username',
                'email',
                'status',
                'must_change_password',
                'created_at',
                'updated_at',
            ]);

        // Search: name, first_name, last_name, username prefix, email prefix, phone_number
        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "{$search}%")
                    ->orWhere('email', 'like', "{$search}%");
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        // Role filter
        if ($request->filled('role')) {
            $role = (string) $request->input('role');
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role)->orWhere('slug', $role);
            });
        }

        // Date filters
        if ($request->filled('created_from')) {
            $query->where('created_at', '>=', (string) $request->input('created_from'));
        }

        if ($request->filled('created_to')) {
            $query->where('created_at', '<=', (string) $request->input('created_to'));
        }

        // Deterministic sorting with whitelist
        $sortBy = (string) $request->input('sort_by', 'created_at');
        if (! in_array($sortBy, self::ALLOWED_SORT_FIELDS, true)) {
            $sortBy = 'created_at';
        }

        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $paginator = $query->orderBy($sortBy, $sortDir)
            ->orderBy('id', $sortDir)
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'Users retrieved successfully.',
            'data' => [
                'current_page' => $paginator->currentPage(),
                'data' => AdminUserListResource::collection($paginator->items()),
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
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request, CreateUserAction $action): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $user = $action->execute($admin, $request->validatedUserData());
        $user->loadMissing(['creator:id,first_name,last_name,name', 'roles']);

        return response()->json([
            'status' => true,
            'message' => 'User created successfully.',
            'data' => new AdminUserResource($user),
        ], 201);
    }

    /**
     * Display the specified user detail with creator summary.
     */
    public function show(User $user): JsonResponse
    {
        $user->loadMissing(['creator:id,first_name,last_name,name', 'roles']);

        return response()->json([
            'status' => true,
            'message' => 'User retrieved successfully.',
            'data' => new AdminUserResource($user),
        ], 200);
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user, UpdateUserAction $action): JsonResponse
    {
        $updatedUser = $action->execute($user, $request->validatedUserData());
        $updatedUser->loadMissing(['creator:id,first_name,last_name,name', 'roles']);

        return response()->json([
            'status' => true,
            'message' => 'User updated successfully.',
            'data' => new AdminUserResource($updatedUser),
        ], 200);
    }

    /**
     * Update the status of the specified user.
     */
    public function updateStatus(
        UpdateUserStatusRequest $request,
        User $user,
        UpdateUserStatusAction $action
    ): JsonResponse {
        $updatedUser = $action->execute($user, (string) $request->input('status'));
        $updatedUser->loadMissing(['creator:id,first_name,last_name,name', 'roles']);

        return response()->json([
            'status' => true,
            'message' => 'User status updated successfully.',
            'data' => new AdminUserResource($updatedUser),
        ], 200);
    }

    /**
     * Resend credentials to the specified user.
     */
    public function resendCredentials(
        Request $request,
        User $user,
        ResendUserCredentialsAction $action
    ): JsonResponse {
        $action->execute($user);

        return response()->json([
            'status' => true,
            'message' => 'Credentials resent successfully.',
        ], 200);
    }

    /**
     * Delete the specified user.
     */
    public function destroy(User $user, DeleteUserAction $action): JsonResponse
    {
        $action->execute($user);

        return response()->json([
            'status' => true,
            'message' => 'User deleted successfully.',
        ], 200);
    }

    /**
     * Bulk delete multiple users.
     */
    public function bulkDestroy(BulkDeleteUsersRequest $request, BulkDeleteUsersAction $action): JsonResponse
    {
        $deletedCount = $action->execute($request->input('ids'));

        return response()->json([
            'status' => true,
            'message' => "{$deletedCount} users deleted successfully.",
            'data' => [
                'deleted_count' => $deletedCount,
            ],
        ], 200);
    }
}
