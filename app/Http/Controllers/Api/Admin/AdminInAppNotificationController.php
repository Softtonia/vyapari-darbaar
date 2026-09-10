<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminInAppNotificationResource;
use App\Services\InAppNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminInAppNotificationController extends Controller
{
    /**
     * Display a paginated listing of user in-app notifications for Admin.
     */
    public function index(Request $request, InAppNotificationService $service): JsonResponse
    {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-in-app.view') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-in-app.view permission.',
            ], 403);
        }

        $paginator = $service->paginateForAdmin($request->all());

        return response()->json([
            'status' => true,
            'message' => 'In-app notifications retrieved successfully.',
            'data' => [
                'current_page' => $paginator->currentPage(),
                'data' => AdminInAppNotificationResource::collection($paginator->items()),
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
     * Display the specified in-app notification detail.
     */
    public function show(Request $request, int $id, InAppNotificationService $service): JsonResponse
    {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-in-app.view') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-in-app.view permission.',
            ], 403);
        }

        $notification = $service->findForAdmin($id);
        if (! $notification) {
            return response()->json([
                'status' => false,
                'message' => 'In-app notification not found.',
                'error' => 'NOT_FOUND',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'In-app notification retrieved successfully.',
            'data' => new AdminInAppNotificationResource($notification),
        ], 200);
    }
}
