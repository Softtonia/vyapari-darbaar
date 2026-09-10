<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Notification\BulkDeleteNotificationDevicesRequest;
use App\Http\Requests\Admin\Notification\UpdateAdminDeviceStatusRequest;
use App\Http\Resources\AdminNotificationDeviceResource;
use App\Models\NotificationDevice;
use App\Services\Firebase\NotificationDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationDeviceController extends Controller
{
    /**
     * Display a paginated listing of notification devices.
     */
    public function index(Request $request, NotificationDeviceService $service): JsonResponse
    {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-device.view') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-device.view permission.',
            ], 403);
        }

        $paginator = $service->paginateForAdmin($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Notification devices retrieved successfully.',
            'data' => [
                'current_page' => $paginator->currentPage(),
                'data' => AdminNotificationDeviceResource::collection($paginator->items()),
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
     * Display the specified notification device detail.
     */
    public function show(Request $request, NotificationDevice $device): JsonResponse
    {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-device.view') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-device.view permission.',
            ], 403);
        }

        $device->loadMissing('user:id,first_name,last_name,name,email,username');

        return response()->json([
            'status' => true,
            'message' => 'Notification device retrieved successfully.',
            'data' => new AdminNotificationDeviceResource($device),
        ], 200);
    }

    /**
     * Update device active status.
     */
    public function updateStatus(
        UpdateAdminDeviceStatusRequest $request,
        NotificationDevice $device,
        NotificationDeviceService $service
    ): JsonResponse {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-device.update') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-device.update permission.',
            ], 403);
        }

        $updated = $service->updateDeviceStatus($device, (bool) $request->validated('is_active'));
        $updated->loadMissing('user:id,first_name,last_name,name,email,username');

        return response()->json([
            'status' => true,
            'message' => 'Notification device status updated successfully.',
            'data' => new AdminNotificationDeviceResource($updated),
        ], 200);
    }

    /**
     * Delete a single notification device.
     */
    public function destroy(
        Request $request,
        NotificationDevice $device,
        NotificationDeviceService $service
    ): JsonResponse {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-device.delete') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-device.delete permission.',
            ], 403);
        }

        $service->deleteDevice($device);

        return response()->json([
            'status' => true,
            'message' => 'Notification device deleted successfully.',
        ], 200);
    }

    /**
     * Bulk delete notification devices.
     */
    public function bulkDestroy(
        BulkDeleteNotificationDevicesRequest $request,
        NotificationDeviceService $service
    ): JsonResponse {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-device.delete') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-device.delete permission.',
            ], 403);
        }

        $deletedCount = $service->bulkDeleteDevices($request->validated('ids'));

        return response()->json([
            'status' => true,
            'message' => "{$deletedCount} notification device(s) deleted successfully.",
            'data' => [
                'deleted_count' => $deletedCount,
            ],
        ], 200);
    }
}
