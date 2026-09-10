<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Notification\ManageTopicUsersRequest;
use App\Http\Requests\Admin\Notification\StoreNotificationTopicRequest;
use App\Http\Requests\Admin\Notification\UpdateNotificationTopicRequest;
use App\Http\Resources\NotificationTopicResource;
use App\Models\NotificationTopic;
use App\Services\NotificationTopicService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationTopicController extends Controller
{
    /**
     * Display a paginated listing of notification topics.
     */
    public function index(Request $request, NotificationTopicService $service): JsonResponse
    {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-topic.view') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-topic.view permission.',
            ], 403);
        }

        $paginator = $service->paginate($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Notification topics retrieved successfully.',
            'data' => [
                'current_page' => $paginator->currentPage(),
                'data' => NotificationTopicResource::collection($paginator->items()),
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
     * Store a newly created notification topic.
     */
    public function store(StoreNotificationTopicRequest $request, NotificationTopicService $service): JsonResponse
    {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-topic.create') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-topic.create permission.',
            ], 403);
        }

        $topic = $service->create($request->validated(), $admin);

        return response()->json([
            'status' => true,
            'message' => 'Notification topic created successfully.',
            'data' => new NotificationTopicResource($topic),
        ], 201);
    }

    /**
     * Display the specified notification topic.
     */
    public function show(Request $request, NotificationTopic $topic): JsonResponse
    {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-topic.view') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-topic.view permission.',
            ], 403);
        }

        $topic->loadMissing('creator:id,first_name,last_name,name');
        $topic->loadCount('users');

        return response()->json([
            'status' => true,
            'message' => 'Notification topic retrieved successfully.',
            'data' => new NotificationTopicResource($topic),
        ], 200);
    }

    /**
     * Update the specified notification topic.
     */
    public function update(
        UpdateNotificationTopicRequest $request,
        NotificationTopic $topic,
        NotificationTopicService $service
    ): JsonResponse {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-topic.update') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-topic.update permission.',
            ], 403);
        }

        $updated = $service->update($topic, $request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Notification topic updated successfully.',
            'data' => new NotificationTopicResource($updated),
        ], 200);
    }

    /**
     * Delete the specified notification topic.
     */
    public function destroy(
        Request $request,
        NotificationTopic $topic,
        NotificationTopicService $service
    ): JsonResponse {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-topic.delete') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-topic.delete permission.',
            ], 403);
        }

        $service->delete($topic);

        return response()->json([
            'status' => true,
            'message' => 'Notification topic deleted successfully.',
        ], 200);
    }

    /**
     * Bulk add users to the topic.
     */
    public function addUsers(
        ManageTopicUsersRequest $request,
        NotificationTopic $topic,
        NotificationTopicService $service
    ): JsonResponse {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-topic.manage-users') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-topic.manage-users permission.',
            ], 403);
        }

        $addedCount = $service->addUsers($topic, $request->validated('user_ids'));

        return response()->json([
            'status' => true,
            'message' => "{$addedCount} user(s) added to topic successfully.",
            'data' => [
                'added_count' => $addedCount,
                'total_users' => $topic->users()->count(),
            ],
        ], 200);
    }

    /**
     * Bulk remove users from the topic.
     */
    public function removeUsers(
        ManageTopicUsersRequest $request,
        NotificationTopic $topic,
        NotificationTopicService $service
    ): JsonResponse {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-topic.manage-users') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-topic.manage-users permission.',
            ], 403);
        }

        $removedCount = $service->removeUsers($topic, $request->validated('user_ids'));

        return response()->json([
            'status' => true,
            'message' => "{$removedCount} user(s) removed from topic successfully.",
            'data' => [
                'removed_count' => $removedCount,
                'total_users' => $topic->users()->count(),
            ],
        ], 200);
    }
}
