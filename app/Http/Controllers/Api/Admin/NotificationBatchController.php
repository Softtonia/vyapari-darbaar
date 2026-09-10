<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationBatchDetailResource;
use App\Http\Resources\NotificationBatchResource;
use App\Models\NotificationBatch;
use App\Services\NotificationBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class NotificationBatchController extends Controller
{
    /**
     * Display a paginated listing of notification batches.
     */
    public function index(Request $request, NotificationBatchService $service): JsonResponse
    {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-batch.view') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-batch.view permission.',
            ], 403);
        }

        $paginator = $service->paginate($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Notification batches retrieved successfully.',
            'data' => [
                'current_page' => $paginator->currentPage(),
                'data' => NotificationBatchResource::collection($paginator->items()),
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
     * Display the specified notification batch detail.
     */
    public function show(Request $request, string $id, NotificationBatchService $service): JsonResponse
    {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-batch.view') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-batch.view permission.',
            ], 403);
        }

        $batch = $service->find($id);
        if (! $batch) {
            return response()->json([
                'status' => false,
                'message' => 'Notification batch not found.',
                'error' => 'NOT_FOUND',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Notification batch retrieved successfully.',
            'data' => new NotificationBatchDetailResource($batch),
        ], 200);
    }

    /**
     * Cancel an active or pending notification batch.
     */
    public function cancel(Request $request, NotificationBatch $batch, NotificationBatchService $service): JsonResponse
    {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-batch.cancel') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-batch.cancel permission.',
            ], 403);
        }

        try {
            $cancelled = $service->cancel($batch);

            return response()->json([
                'status' => true,
                'message' => 'Notification batch cancelled successfully.',
                'data' => new NotificationBatchDetailResource($cancelled),
            ], 200);
        } catch (RuntimeException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'error' => 'CANNOT_CANCEL_BATCH',
            ], 422);
        }
    }

    /**
     * Retry failed recipients of a batch by creating a new child batch preserving original history.
     */
    public function retryFailed(
        Request $request,
        NotificationBatch $batch,
        NotificationBatchService $service
    ): JsonResponse {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-batch.retry') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-batch.retry permission.',
            ], 403);
        }

        try {
            $childBatch = $service->retryFailed($batch, $admin);

            return response()->json([
                'status' => true,
                'message' => 'Retry notification batch created and queued successfully.',
                'data' => new NotificationBatchDetailResource($childBatch),
            ], 201);
        } catch (RuntimeException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'error' => 'RETRY_FAILED',
            ], 422);
        }
    }
}
