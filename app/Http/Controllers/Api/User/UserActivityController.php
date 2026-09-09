<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserActivityResource;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserActivityController extends Controller
{
    /**
     * Display a listing of the user's activities.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = UserActivity::query()
            ->where('user_id', $user->id)
            ->latest('id');

        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 20)));
        $activities = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'User activities retrieved successfully.',
            'data' => [
                'items' => UserActivityResource::collection($activities->items()),
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
