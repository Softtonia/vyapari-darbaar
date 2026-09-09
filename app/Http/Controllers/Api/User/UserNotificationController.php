<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserNotificationResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserNotificationController extends Controller
{
    /**
     * Display a listing of the user's notifications.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = $user->notifications();

        $status = strtolower((string) $request->input('status', 'all'));
        if ($status === 'unread') {
            $query = $user->unreadNotifications();
        } elseif ($status === 'read') {
            $query = $user->readNotifications();
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 20)));
        $notifications = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'Notifications retrieved successfully.',
            'data' => [
                'items' => UserNotificationResource::collection($notifications->items()),
                'unread_count' => $user->unreadNotifications()->count(),
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                    'last_page' => $notifications->lastPage(),
                ],
            ],
        ], 200);
    }

    /**
     * Get the count of unread notifications for badge counters.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'status' => true,
            'message' => 'Unread notification count retrieved successfully.',
            'data' => [
                'unread_count' => $user->unreadNotifications()->count(),
            ],
        ], 200);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $notification = $user->notifications()->where('id', $id)->first();

        if (! $notification) {
            return response()->json([
                'status' => false,
                'message' => 'Notification not found.',
            ], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'status' => true,
            'message' => 'Notification marked as read successfully.',
            'data' => new UserNotificationResource($notification->fresh()),
        ], 200);
    }

    /**
     * Mark all unread notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'status' => true,
            'message' => 'All notifications marked as read successfully.',
            'data' => [
                'unread_count' => 0,
            ],
        ], 200);
    }

    /**
     * Delete/dismiss a specific notification.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $notification = $user->notifications()->where('id', $id)->first();

        if (! $notification) {
            return response()->json([
                'status' => false,
                'message' => 'Notification not found.',
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'status' => true,
            'message' => 'Notification deleted successfully.',
        ], 200);
    }

    /**
     * Clear all read notifications.
     */
    public function clearRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $deletedCount = $user->readNotifications()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Read notifications cleared successfully.',
            'data' => [
                'deleted_count' => $deletedCount,
            ],
        ], 200);
    }
}
