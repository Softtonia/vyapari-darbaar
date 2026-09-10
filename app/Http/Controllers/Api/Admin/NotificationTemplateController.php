<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Notification\BulkDeleteNotificationTemplatesRequest;
use App\Http\Requests\Admin\Notification\StoreNotificationTemplateRequest;
use App\Http\Requests\Admin\Notification\UpdateNotificationTemplateRequest;
use App\Http\Resources\NotificationTemplateResource;
use App\Models\NotificationTemplate;
use App\Services\NotificationTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationTemplateController extends Controller
{
    /**
     * Display a paginated listing of notification templates.
     */
    public function index(Request $request, NotificationTemplateService $service): JsonResponse
    {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-template.view') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-template.view permission.',
            ], 403);
        }

        $paginator = $service->paginate($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Notification templates retrieved successfully.',
            'data' => [
                'current_page' => $paginator->currentPage(),
                'data' => NotificationTemplateResource::collection($paginator->items()),
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
     * Store a newly created notification template.
     */
    public function store(StoreNotificationTemplateRequest $request, NotificationTemplateService $service): JsonResponse
    {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-template.create') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-template.create permission.',
            ], 403);
        }

        $template = $service->create($request->validated(), $admin);

        return response()->json([
            'status' => true,
            'message' => 'Notification template created successfully.',
            'data' => new NotificationTemplateResource($template),
        ], 201);
    }

    /**
     * Display the specified notification template.
     */
    public function show(Request $request, NotificationTemplate $template): JsonResponse
    {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-template.view') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-template.view permission.',
            ], 403);
        }

        $template->loadMissing('creator:id,first_name,last_name,name');

        return response()->json([
            'status' => true,
            'message' => 'Notification template retrieved successfully.',
            'data' => new NotificationTemplateResource($template),
        ], 200);
    }

    /**
     * Update the specified notification template.
     */
    public function update(
        UpdateNotificationTemplateRequest $request,
        NotificationTemplate $template,
        NotificationTemplateService $service
    ): JsonResponse {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-template.update') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-template.update permission.',
            ], 403);
        }

        $updated = $service->update($template, $request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Notification template updated successfully.',
            'data' => new NotificationTemplateResource($updated),
        ], 200);
    }

    /**
     * Delete the specified notification template.
     */
    public function destroy(
        Request $request,
        NotificationTemplate $template,
        NotificationTemplateService $service
    ): JsonResponse {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-template.delete') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-template.delete permission.',
            ], 403);
        }

        $service->delete($template);

        return response()->json([
            'status' => true,
            'message' => 'Notification template deleted successfully.',
        ], 200);
    }

    /**
     * Bulk delete multiple notification templates.
     */
    public function bulkDestroy(
        BulkDeleteNotificationTemplatesRequest $request,
        NotificationTemplateService $service
    ): JsonResponse {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-template.delete') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-template.delete permission.',
            ], 403);
        }

        $deletedCount = $service->bulkDelete($request->validated('ids'));

        return response()->json([
            'status' => true,
            'message' => "{$deletedCount} notification template(s) deleted successfully.",
            'data' => [
                'deleted_count' => $deletedCount,
            ],
        ], 200);
    }
}
