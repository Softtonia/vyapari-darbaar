<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Notification\PreviewNotificationRequest;
use App\Http\Requests\Admin\Notification\SendNotificationRequest;
use App\Http\Resources\NotificationBatchResource;
use App\Services\NotificationSendService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class NotificationSendController extends Controller
{
    /**
     * Preview notification content and audience estimation without dispatching.
     */
    public function preview(PreviewNotificationRequest $request, NotificationSendService $service): JsonResponse
    {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-send.create') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-send.create permission.',
            ], 403);
        }

        try {
            $preview = $service->preview($request->validated());

            return response()->json([
                'status' => true,
                'message' => 'Notification preview generated successfully.',
                'data' => $preview,
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'error' => 'INVALID_NOTIFICATION_CONTENT',
            ], 422);
        }
    }

    /**
     * Create and dispatch notification campaign batch.
     */
    public function send(SendNotificationRequest $request, NotificationSendService $service): JsonResponse
    {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-send.create') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-send.create permission.',
            ], 403);
        }

        try {
            $batch = $service->send($request->validated(), $admin);

            return response()->json([
                'status' => true,
                'message' => $batch->status->value === 'scheduled'
                    ? 'Notification campaign scheduled successfully.'
                    : 'Notification campaign queued successfully.',
                'data' => new NotificationBatchResource($batch),
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'error' => 'INVALID_CAMPAIGN_DATA',
            ], 422);
        }
    }
}
