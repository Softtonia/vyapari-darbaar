<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\Profile\UpdateAdminProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendAdminEmailOtpRequest;
use App\Http\Requests\Admin\UpdateAdminProfileRequest;
use App\Http\Resources\AdminProfileResource;
use App\Models\Admin;
use App\Notifications\AdminEmailUpdateOtpNotification;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class AdminProfileController extends Controller
{
    /**
     * Display the authenticated administrator profile.
     */
    public function profile(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $admin->loadMissing('roles');

        return response()->json([
            'status' => true,
            'message' => 'Admin profile retrieved successfully.',
            'data' => new AdminProfileResource($admin),
        ], 200);
    }

    /**
     * Send email verification OTP for admin email update.
     */
    public function sendEmailOtp(
        SendAdminEmailOtpRequest $request,
        OtpService $otpService
    ): JsonResponse {
        $targetEmail = $request->normalizedEmail();

        $cooldown = $otpService->checkResendCooldown($targetEmail, 'admin_email_update', 60);
        if (! $cooldown['can_resend']) {
            return response()->json([
                'status' => false,
                'message' => "Please wait {$cooldown['cooldown_remaining_seconds']} seconds before requesting a new OTP.",
            ], 429);
        }

        $otpData = $otpService->getOrCreateOtp(
            identifier: $targetEmail,
            purpose: 'admin_email_update',
            expiryMinutes: 10
        );

        $otpService->setResendCooldown($targetEmail, 'admin_email_update', 60);

        Notification::route('mail', $targetEmail)->notify(
            new AdminEmailUpdateOtpNotification($otpData['otp'])
        );

        return response()->json([
            'status' => true,
            'message' => 'OTP has been sent to the email address. Valid for 10 minutes.',
            'data' => [
                'remaining_seconds' => $otpData['remaining_seconds'],
            ],
        ], 200);
    }

    /**
     * Update the authenticated administrator profile.
     */
    public function update(
        UpdateAdminProfileRequest $request,
        UpdateAdminProfileAction $action
    ): JsonResponse {
        /** @var Admin $admin */
        $admin = $request->user();

        $updatedAdmin = $action->execute($admin, $request->validatedProfileData());

        return response()->json([
            'status' => true,
            'message' => 'Admin profile updated successfully.',
            'data' => new AdminProfileResource($updatedAdmin),
        ], 200);
    }
}
