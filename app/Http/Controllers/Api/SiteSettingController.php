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

    /**
     * Display public social links.
     */
    public function socialLinks(SiteSettingService $service): JsonResponse
    {
        $setting = $service->getPublicSettings();

        return response()->json([
            'status' => true,
            'message' => 'Social links fetched successfully.',
            'data' => $setting && ! empty($setting->social_links) ? $setting->social_links : (object) [],
        ], 200);
    }
}
