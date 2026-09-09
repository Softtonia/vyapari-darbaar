<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicSiteSettingResource;
use App\Services\SiteSettingService;
use Illuminate\Http\JsonResponse;

class SiteSettingController extends Controller
{
    /**
     * Display public site settings.
     */
    public function show(SiteSettingService $service): JsonResponse
    {
        $setting = $service->getPublicSettings();

        return response()->json([
            'status' => true,
            'message' => 'Site settings fetched successfully.',
            'data' => $setting ? new PublicSiteSettingResource($setting) : null,
        ], 200);
    }
}
