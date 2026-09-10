<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\NotificationDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationDashboardController extends Controller
{
    /**
     * Display aggregated notification metrics, failures, platform distribution, and chart data.
     */
    public function index(Request $request, NotificationDashboardService $service): JsonResponse
    {
        $admin = $request->user();
        if ($admin && ! $admin->can('notification-dashboard.view') && ! $admin->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing notification-dashboard.view permission.',
            ], 403);
        }

        $days = (int) $request->input('days', 30);
        if (! in_array($days, [7, 30], true)) {
            $days = 30;
        }

        $bypassCache = filter_var($request->input('refresh', false), FILTER_VALIDATE_BOOLEAN);

        $stats = $service->getDashboardStats($days, $bypassCache);

        return response()->json([
            'status' => true,
            'message' => 'Notification dashboard statistics retrieved successfully.',
            'data' => $stats,
        ], 200);
    }
}
