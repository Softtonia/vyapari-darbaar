<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicFirebaseConfigResource;
use App\Services\Firebase\FirebaseConfigService;
use Illuminate\Http\JsonResponse;

class PublicFirebaseConfigController extends Controller
{
    /**
     * Retrieve the public Firebase web configuration.
     */
    public function show(FirebaseConfigService $service): JsonResponse
    {
        $config = $service->getPublicConfig();

        if (! $config) {
            return response()->json([
                'status' => false,
                'message' => 'Firebase notifications are currently unavailable.',
                'error' => 'FIREBASE_NOT_AVAILABLE',
            ], 503);
        }

        return response()->json([
            'status' => true,
            'message' => 'Firebase configuration retrieved successfully.',
            'data' => new PublicFirebaseConfigResource($config),
        ], 200);
    }
}
