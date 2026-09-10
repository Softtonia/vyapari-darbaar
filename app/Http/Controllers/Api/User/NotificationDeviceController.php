<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\NotificationDevice\DeleteNotificationDeviceRequest;
use App\Http\Requests\User\NotificationDevice\StoreNotificationDeviceRequest;
use App\Http\Resources\NotificationDeviceResource;
use App\Models\User;
use App\Services\Firebase\NotificationDeviceService;
use Illuminate\Http\JsonResponse;

class NotificationDeviceController extends Controller
{
    /**
     * Register or reactivate a user's notification device token.
     */
    public function store(
        StoreNotificationDeviceRequest $request,
        NotificationDeviceService $service
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $device = $service->registerDevice($user, $request->validated(), $request->ip());

        return response()->json([
            'status' => true,
            'message' => 'Notification device registered successfully.',
            'data' => new NotificationDeviceResource($device),
        ], 200);
    }

    /**
     * Deactivate a notification device for the authenticated user.
     */
    public function destroy(
        DeleteNotificationDeviceRequest $request,
        NotificationDeviceService $service
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $deactivated = $service->deactivateUserDevice(
            $user,
            (string) $request->validated('fcm_token')
        );

        if (! $deactivated) {
            return response()->json([
                'status' => false,
                'message' => 'Notification device not found.',
                'error' => 'DEVICE_NOT_FOUND',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Notification device deactivated successfully.',
        ], 200);
    }
}
