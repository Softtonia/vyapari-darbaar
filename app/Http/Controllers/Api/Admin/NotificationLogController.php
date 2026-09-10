<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationLogResource;
use App\Services\NotificationLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationLogController extends Controller
{
    /**
     * Display a paginated listing of notification delivery logs.
     */
    public function index(Request $request, NotificationLogService $service): JsonResponse
    {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-log.view') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-log.view permission.',
            ], 403);
        }

        $paginator = $service->paginate($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Notification logs retrieved successfully.',
            'data' => [
                'current_page' => $paginator->currentPage(),
                'data' => NotificationLogResource::collection($paginator->items()),
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
     * Display the specified notification log detail.
     */
    public function show(Request $request, int $id, NotificationLogService $service): JsonResponse
    {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-log.view') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-log.view permission.',
            ], 403);
        }

        $log = $service->find($id);
        if (! $log) {
            return response()->json([
                'status' => false,
                'message' => 'Notification log not found.',
                'error' => 'NOT_FOUND',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Notification log retrieved successfully.',
            'data' => new NotificationLogResource($log),
        ], 200);
    }
}
