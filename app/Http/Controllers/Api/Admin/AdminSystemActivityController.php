<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SystemActivityResource;
use App\Models\UserActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminSystemActivityController extends Controller
{
    /**
     * Display a paginated list of system activity logs.
     */
    public function index(Request $request): JsonResponse
    {
        $query = UserActivity::query()->with(['user.roles']);

        // 1. Module filter
        if ($request->filled('module')) {
            $modules = array_filter(array_map('trim', explode(',', (string) $request->input('module'))));
            if (count($modules) === 1) {
                $query->where('module', $modules[0]);
            } elseif (count($modules) > 1) {
                $query->whereIn('module', $modules);
            }
        }

        // 2. Action filter
        if ($request->filled('action')) {
            $actions = array_filter(array_map('trim', explode(',', (string) $request->input('action'))));
            if (count($actions) === 1) {
                $query->where('action', $actions[0]);
            } elseif (count($actions) > 1) {
                $query->whereIn('action', $actions);
            }
        }

        // 3. Status filter
        if ($request->filled('status')) {
            $status = trim((string) $request->input('status'));
            $query->where('status', 'like', $status);
        }

        // 4. User ID filter
        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        // 5. Date filters
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }

        // 6. Search keyword
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $query->where(function (Builder $q) use ($escaped) {
                $q->where('description', 'like', "%{$escaped}%")
                    ->orWhere('module', 'like', "%{$escaped}%")
                    ->orWhere('action', 'like', "%{$escaped}%")
                    ->orWhere('ip_address', 'like', "%{$escaped}%")
                    ->orWhereHas('user', function (Builder $uq) use ($escaped) {
                        $uq->where('full_name', 'like', "%{$escaped}%")
                            ->orWhere('username', 'like', "%{$escaped}%")
                            ->orWhere('email', 'like', "%{$escaped}%");
                    });
            });
        }

        // 7. Sorting
        $allowedSorts = ['id', 'created_at', 'module', 'action', 'status'];
        $sortBy = (string) $request->input('sort_by', 'id');
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'id';
        }
        $sortOrder = strtolower((string) $request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // 8. Pagination
        $perPage = min(100, max(1, (int) $request->input('per_page', 20)));
        $paginator = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'System activity logs retrieved successfully.',
            'data' => [
                'total_logs' => $paginator->total(),
                'items' => SystemActivityResource::collection($paginator->items()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ], 200);
    }

    /**
     * Display a specific activity log entry.
     */
    public function show(int $id): JsonResponse
    {
        $log = UserActivity::query()->with(['user.roles'])->find($id);

        if (! $log) {
            return response()->json([
                'status' => false,
                'message' => 'Activity log entry not found.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Activity log details retrieved successfully.',
            'data' => new SystemActivityResource($log),
        ], 200);
    }

    /**
     * Get available distinct modules with their counts.
     */
    public function modules(): JsonResponse
    {
        $modules = UserActivity::query()
            ->select('module', DB::raw('count(*) as count'))
            ->whereNotNull('module')
            ->groupBy('module')
            ->orderBy('module')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Modules list retrieved successfully.',
            'data' => $modules,
        ], 200);
    }

    /**
     * Get available distinct actions list.
     */
    public function actions(): JsonResponse
    {
        $actions = UserActivity::query()
            ->select('action', DB::raw('count(*) as count'))
            ->whereNotNull('action')
            ->groupBy('action')
            ->orderBy('action')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Actions list retrieved successfully.',
            'data' => $actions,
        ], 200);
    }

    /**
     * Delete a single activity log entry.
     */
    public function destroy(int $id): JsonResponse
    {
        $log = UserActivity::query()->find($id);

        if (!$log) {
            return response()->json([
                'status' => false,
                'message' => 'Activity log entry not found.',
            ], 404);
        }

        $log->delete();

        return response()->json([
            'status' => true,
            'message' => 'Activity log entry deleted successfully.',
        ], 200);
    }

    /**
     * Bulk delete activity log entries.
     *
     * Request body: { "ids": [1, 2, 3] }
     * Or delete all: { "delete_all": true }
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        if ($request->boolean('delete_all')) {
            $count = UserActivity::query()->delete();

            return response()->json([
                'status' => true,
                'message' => "All {$count} activity log entries deleted successfully.",
                'data' => ['deleted_count' => $count],
            ], 200);
        }

        $ids = $request->input('ids', []);

        if (!is_array($ids) || empty($ids)) {
            return response()->json([
                'status' => false,
                'message' => 'Please provide an array of IDs to delete, or set delete_all=true to clear all logs.',
            ], 422);
        }

        $ids = array_filter(array_map('intval', $ids));

        if (empty($ids)) {
            return response()->json([
                'status' => false,
                'message' => 'No valid IDs provided.',
            ], 422);
        }

        $count = UserActivity::query()->whereIn('id', $ids)->delete();

        return response()->json([
            'status' => true,
            'message' => "{$count} activity log " . ($count === 1 ? 'entry' : 'entries') . ' deleted successfully.',
            'data' => ['deleted_count' => $count],
        ], 200);
    }
}
