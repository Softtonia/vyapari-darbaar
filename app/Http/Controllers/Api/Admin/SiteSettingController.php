<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SiteSetting\UpdateSiteSettingRequest;
use App\Http\Resources\AdminSiteSettingResource;
use App\Services\SiteSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteSettingController extends Controller
{
    /**
     * Display the site settings for admin.
     */
    public function show(Request $request, SiteSettingService $service): JsonResponse
    {
        $admin = $request->user();
        if ($admin && ! $admin->can('site-setting.view')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing site-setting.view permission.',
            ], 403);
        }

        $setting = $service->getAdminSettings();

        return response()->json([
            'status' => true,
            'message' => 'Site settings fetched successfully.',
            'data' => new AdminSiteSettingResource($setting),
        ], 200);
    }

    /**
     * Update the site settings.
     */
    public function update(UpdateSiteSettingRequest $request, SiteSettingService $service): JsonResponse
    {
        $admin = $request->user();
        if ($admin && ! $admin->can('site-setting.update')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing site-setting.update permission.',
            ], 403);
        }

        $setting = $service->updateSettings(
            $request->validated(),
            $admin?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Site settings updated successfully.',
            'data' => new AdminSiteSettingResource($setting),
        ], 200);
    }
}
