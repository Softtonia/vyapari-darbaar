<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SmtpSetting\TestSmtpSettingRequest;
use App\Http\Requests\Admin\SmtpSetting\UpdateSmtpSettingRequest;
use App\Http\Resources\SmtpSettingResource;
use App\Services\DynamicMailConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SmtpSettingController extends Controller
{
    /**
     * Display the current SMTP settings.
     */
    public function show(Request $request, DynamicMailConfigService $service): JsonResponse
    {
        $admin = $request->user();
        if ($admin && ! $admin->can('smtp-setting.view')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing smtp-setting.view permission.',
            ], 403);
        }

        $setting = $service->getSettings();

        if (! $setting) {
            return response()->json([
                'status' => true,
                'message' => 'SMTP settings not configured.',
                'data' => null,
            ], 200);
        }

        return response()->json([
            'status' => true,
            'message' => 'SMTP settings fetched successfully.',
            'data' => new SmtpSettingResource($setting),
        ], 200);
    }

    /**
     * Update or create the singleton SMTP settings.
     */
    public function update(UpdateSmtpSettingRequest $request, DynamicMailConfigService $service): JsonResponse
    {
        $admin = $request->user();
        if ($admin && ! $admin->can('smtp-setting.update')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing smtp-setting.update permission.',
            ], 403);
        }

        $setting = $service->updateSettings($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'SMTP settings updated successfully.',
            'data' => new SmtpSettingResource($setting),
        ], 200);
    }

    /**
     * Test the saved SMTP settings by sending a diagnostic test email.
     */
    public function test(TestSmtpSettingRequest $request, DynamicMailConfigService $service): JsonResponse
    {
        $admin = $request->user();
        if ($admin && ! $admin->can('smtp-setting.test')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized action. Missing smtp-setting.test permission.',
            ], 403);
        }

        try {
            $service->sendDiagnosticEmail($request->validated('recipient'));

            return response()->json([
                'status' => true,
                'message' => 'SMTP test email sent successfully.',
            ], 200);
        } catch (\RuntimeException $e) {
            return response()->json([
                'status' => false,
                'message' => 'SMTP configuration is not configured.',
                'error' => 'SMTP_CONFIGURATION_MISSING',
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to send SMTP test email.',
                'error' => 'SMTP_TEST_FAILED',
            ], 422);
        }
    }
}
