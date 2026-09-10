<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\InAppNotificationResource;
use App\Models\User;
use App\Services\InAppNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InAppNotificationController extends Controller
{
    /**
     * Display a paginated listing of the authenticated user's notifications.
     */
    public function index(Request $request, InAppNotificationService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $paginator = $service->paginateForUser($user, $request->all());

        return response()->json([
            'status' => true,
            'message' => 'Notifications retrieved successfully.',
            'data' => [
                'items' => InAppNotificationResource::collection($paginator->items()),
                'unread_count' => $service->unreadCountForUser($user),
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
     * Get the count of unread notifications for badge counters.
     */
    public function unreadCount(Request $request, InAppNotificationService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'status' => true,
            'message' => 'Unread notification count retrieved successfully.',
            'data' => [
                'unread_count' => $service->unreadCountForUser($user),
            ],
        ], 200);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, string|int $id, InAppNotificationService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $notification = $service->markAsReadForUser($user, (int) $id);

        if (! $notification) {
            return response()->json([
                'status' => false,
                'message' => 'Notification not found.',
                'error' => 'NOT_FOUND',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Notification marked as read successfully.',
            'data' => new InAppNotificationResource($notification),
        ], 200);
    }

    /**
     * Mark all unread notifications as read.
     */
    public function markAllAsRead(Request $request, InAppNotificationService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $service->markAllAsReadForUser($user);

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
    public function destroy(Request $request, string|int $id, InAppNotificationService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $deleted = $service->deleteForUser($user, (int) $id);

        if (! $deleted) {
            return response()->json([
                'status' => false,
                'message' => 'Notification not found.',
                'error' => 'NOT_FOUND',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Notification deleted successfully.',
        ], 200);
    }

    /**
     * Clear all read notifications.
     */
    public function clearRead(Request $request, InAppNotificationService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $deletedCount = $service->clearReadForUser($user);

        return response()->json([
            'status' => true,
            'message' => 'Read notifications cleared successfully.',
            'data' => [
                'deleted_count' => $deletedCount,
            ],
        ], 200);
    }
}
