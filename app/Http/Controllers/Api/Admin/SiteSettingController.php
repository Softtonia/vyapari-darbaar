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

    /**
     * Display social links for admin.
     */
    public function socialLinks(Request $request, SiteSettingService $service): JsonResponse
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
            'message' => 'Social links fetched successfully.',
            'data' => ! empty($setting->social_links) ? $setting->social_links : (object) [],
        ], 200);
    }

    /**
     * Update social links.
     */
    public function updateSocialLinks(Request $request, SiteSettingService $service): JsonResponse
    {
        $admin = $request->user();
        if ($admin && ! $admin->can('site-setting.update')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing site-setting.update permission.',
            ], 403);
        }

        $raw = $request->input('social_links', $request->all());
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            }
        }

        if (! is_array($raw)) {
            return response()->json([
                'status' => false,
                'message' => 'The social links must be an array or valid JSON object.',
            ], 422);
        }

        $cleaned = [];
        foreach ($raw as $key => $val) {
            if (in_array($key, ['_method', '_token', 'social_links'], true)) {
                continue;
            }
            if (is_string($val)) {
                $trimmed = trim($val);
                $cleaned[$key] = $trimmed !== '' ? $trimmed : null;
            } elseif ($val === null) {
                $cleaned[$key] = null;
            }
        }

        $setting = $service->updateSocialLinks($cleaned, $admin?->id);

        return response()->json([
            'status' => true,
            'message' => 'Social links updated successfully.',
            'data' => ! empty($setting->social_links) ? $setting->social_links : (object) [],
        ], 200);
    }
}
