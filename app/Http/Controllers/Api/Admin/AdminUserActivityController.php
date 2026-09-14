<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminUserActivityResource;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserActivityController extends Controller
{
    /**
     * Allowed columns for sorting activities.
     *
     * @var list<string>
     */
    protected const ALLOWED_SORT_FIELDS = [
        'id',
        'created_at',
        'event',
    ];

    /**
     * Display a global paginated feed of user activities with search and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = UserActivity::query()->with(['user.roles']);

        // Filter by user_id
        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        // Filter by event
        if ($request->filled('event')) {
            $events = array_map('trim', explode(',', (string) $request->input('event')));
            if (count($events) === 1) {
                $query->where('event', $events[0]);
            } else {
                $query->whereIn('event', $events);
            }
        }

        // Filter by IP address
        if ($request->filled('ip_address')) {
            $query->where('ip_address', trim((string) $request->input('ip_address')));
        }

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->where('created_at', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->where('created_at', '<=', $request->input('to_date'));
        }

        // Search in description, event, IP, or user properties
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('event', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Sorting
        $sortBy = (string) $request->input('sort_by', 'id');
        if (! in_array($sortBy, self::ALLOWED_SORT_FIELDS, true)) {
            $sortBy = 'id';
        }
        $sortOrder = strtolower((string) $request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min(100, max(1, (int) $request->input('per_page', 20)));
        $activities = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'User activities retrieved successfully.',
            'data' => [
                'items' => AdminUserActivityResource::collection($activities->items()),
                'pagination' => [
                    'current_page' => $activities->currentPage(),
                    'per_page' => $activities->perPage(),
                    'total' => $activities->total(),
                    'last_page' => $activities->lastPage(),
                ],
            ],
        ], 200);
    }

    /**
     * Display activities for a specific user.
     */
    public function userActivities(Request $request, User $user): JsonResponse
    {
        $query = UserActivity::query()
            ->where('user_id', $user->id)
            ->with(['user.roles']);

        // Filter by event
        if ($request->filled('event')) {
            $events = array_map('trim', explode(',', (string) $request->input('event')));
            if (count($events) === 1) {
                $query->where('event', $events[0]);
            } else {
                $query->whereIn('event', $events);
            }
        }

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->where('created_at', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->where('created_at', '<=', $request->input('to_date'));
        }

        // Search within user's activity descriptions
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('event', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        $sortBy = (string) $request->input('sort_by', 'id');
        if (! in_array($sortBy, self::ALLOWED_SORT_FIELDS, true)) {
            $sortBy = 'id';
        }
        $sortOrder = strtolower((string) $request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min(100, max(1, (int) $request->input('per_page', 20)));
        $activities = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'User activities retrieved successfully.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'status' => $user->status,
                ],
                'items' => AdminUserActivityResource::collection($activities->items()),
                'pagination' => [
                    'current_page' => $activities->currentPage(),
                    'per_page' => $activities->perPage(),
                    'total' => $activities->total(),
                    'last_page' => $activities->lastPage(),
                ],
            ],
        ], 200);
    }
}
