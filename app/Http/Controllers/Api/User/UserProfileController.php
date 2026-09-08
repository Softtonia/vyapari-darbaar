<?php

namespace App\Http\Controllers\Api\User;

use App\Actions\User\Profile\UpdateUserProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\SendUserEmailOtpRequest;
use App\Http\Requests\User\UpdateUserProfileRequest;
use App\Http\Resources\UserProfileResource;
use App\Models\User;
use App\Notifications\UserEmailUpdateOtpNotification;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class UserProfileController extends Controller
{
    /**
     * Display the authenticated user's profile.
     */
    public function profile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles');

        return response()->json([
            'status' => true,
            'message' => 'User profile retrieved successfully.',
            'data' => new UserProfileResource($user),
        ], 200);
    }

    /**
     * Send email verification OTP for user email update.
     */
    public function sendEmailOtp(
        SendUserEmailOtpRequest $request,
        OtpService $otpService
    ): JsonResponse {
        $targetEmail = $request->normalizedEmail();

        $cooldown = $otpService->checkResendCooldown($targetEmail, 'user_email_update', 60);
        if (! $cooldown['can_resend']) {
            return response()->json([
                'status' => false,
                'message' => "Please wait {$cooldown['cooldown_remaining_seconds']} seconds before requesting a new OTP.",
            ], 429);
        }

        $otpData = $otpService->getOrCreateOtp(
            identifier: $targetEmail,
            purpose: 'user_email_update',
            expiryMinutes: 10
        );

        $otpService->setResendCooldown($targetEmail, 'user_email_update', 60);

        Notification::route('mail', $targetEmail)->notify(
            new UserEmailUpdateOtpNotification($otpData['otp'])
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
     * Update the authenticated user profile.
     */
    public function update(
        UpdateUserProfileRequest $request,
        UpdateUserProfileAction $action
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $updatedUser = $action->execute($user, $request->validatedProfileData());

        return response()->json([
            'status' => true,
            'message' => 'User profile updated successfully.',
            'data' => new UserProfileResource($updatedUser),
        ], 200);
    }
}
