<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Firebase\TestFirebaseNotificationRequest;
use App\Http\Requests\Admin\Firebase\UpdateFirebaseSettingRequest;
use App\Http\Resources\FirebaseSettingResource;
use App\Services\Firebase\FcmService;
use App\Services\Firebase\FirebaseConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class FirebaseSettingController extends Controller
{
    /**
     * Display the current Firebase settings.
     */
    public function show(Request $request, FirebaseConfigService $service): JsonResponse
    {
        $admin = $request->user();
        if ($admin && ! $admin->can('firebase-setting.view')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing firebase-setting.view permission.',
            ], 403);
        }

        $setting = $service->getSettings();

        if (! $setting) {
            return response()->json([
                'status' => true,
                'message' => 'Firebase settings not configured.',
                'data' => null,
            ], 200);
        }

        return response()->json([
            'status' => true,
            'message' => 'Firebase settings retrieved successfully.',
            'data' => new FirebaseSettingResource($setting),
        ], 200);
    }

    /**
     * Update or create the singleton Firebase settings.
     */
    public function update(UpdateFirebaseSettingRequest $request, FirebaseConfigService $service): JsonResponse
    {
        $admin = $request->user();
        if ($admin && ! $admin->can('firebase-setting.update')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing firebase-setting.update permission.',
            ], 403);
        }

        $setting = $service->updateSettings($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Firebase settings updated successfully.',
            'data' => new FirebaseSettingResource($setting),
        ], 200);
    }

    /**
     * Test the saved Firebase settings by sending a test notification.
     */
    public function test(TestFirebaseNotificationRequest $request, FcmService $fcmService): JsonResponse
    {
        $admin = $request->user();
        if ($admin && ! $admin->can('firebase-setting.test')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing firebase-setting.test permission.',
            ], 403);
        }

        try {
            $fcmService->sendTestNotification(
                (string) $request->validated('fcm_token'),
                $request->validated('title'),
                $request->validated('body')
            );

            return response()->json([
                'status' => true,
                'message' => 'Firebase test notification sent successfully.',
            ], 200);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'FIREBASE_CONFIGURATION_MISSING') {
                return response()->json([
                    'status' => false,
                    'message' => 'Firebase configuration is missing.',
                    'error' => 'FIREBASE_CONFIGURATION_MISSING',
                ], 422);
            }

            $errorCode = in_array($e->getMessage(), ['FIREBASE_AUTH_FAILED', 'FCM_TOKEN_INVALID', 'FIREBASE_REQUEST_FAILED'], true)
                ? $e->getMessage()
                : 'FIREBASE_TEST_FAILED';

            return response()->json([
                'status' => false,
                'message' => 'Failed to send Firebase test notification.',
                'error' => $errorCode,
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to send Firebase test notification.',
                'error' => 'FIREBASE_TEST_FAILED',
            ], 422);
        }
    }
}
