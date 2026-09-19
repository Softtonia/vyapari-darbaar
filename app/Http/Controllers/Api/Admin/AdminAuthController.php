<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\ForgotPasswordAdminAction;
use App\Actions\Admin\LoginAdminAction;
use App\Actions\Admin\LogoutAdminAction;
use App\Actions\Admin\LogoutAllAdminSessionsAction;
use App\Actions\Admin\ResetPasswordAdminAction;
use App\Actions\Admin\VerifyResetTokenAdminAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ForgotPasswordAdminRequest;
use App\Http\Requests\Admin\LoginAdminRequest;
use App\Http\Requests\Admin\ResetPasswordAdminRequest;
use App\Http\Requests\Admin\SendAdminOtpRequest;
use App\Http\Requests\Admin\VerifyResetTokenAdminRequest;
use App\Models\Admin;
use App\Notifications\AdminOtpNotification;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class AdminAuthController extends Controller
{
    /**
     * Authenticate an administrator and issue a Sanctum token (supports Password or OTP).
     */
    public function login(LoginAdminRequest $request, LoginAdminAction $action): JsonResponse
    {
        $deviceName = (string) $request->input('device_name', $request->header('User-Agent', 'admin-token'));
        $result = $action->execute($request->credentials(), $deviceName);

        if (! $result['success']) {
            return response()->json([
                'status' => false,
                'message' => $result['message'],
            ], $result['code']);
        }

        return response()->json([
            'status' => true,
            'message' => $result['message'],
            'data' => $result['data'],
        ], 200);
    }

    /**
     * Send an OTP specifically for administrator login.
     */
    public function sendLoginOtp(SendAdminOtpRequest $request, OtpService $otpService): JsonResponse
    {
        return $this->sendOtp($request, $otpService);
    }

    /**
     * Send an OTP to the administrator (email or mobile).
     */
    public function sendOtp(SendAdminOtpRequest $request, OtpService $otpService): JsonResponse
    {
        $targetAdmin = $request->targetAdmin();

        if (! $targetAdmin) {
            return response()->json([
                'status' => false,
                'message' => 'No administrative account found with the provided credentials.',
            ], 404);
        }

        if ($targetAdmin->status !== 'active') {
            return response()->json([
                'status' => false,
                'message' => 'Account is inactive. Please contact the administrator.',
            ], 403);
        }

        $inputEmail = $request->input('email');
        $inputPhone = $request->input('phone_number') ?? $request->input('mobile') ?? $request->input('phone');
        $identifier = $request->input('identifier');

        $email = $targetAdmin->email ?? ($inputEmail ? strtolower(trim((string) $inputEmail)) : null);
        $phone = $targetAdmin->phone_number ?? $inputPhone;
        $isMobileRequest = ! empty($inputPhone) && (empty($inputEmail) || $identifier === $inputPhone);
        $primaryTarget = $email ?? $phone ?? $identifier;

        $purpose = 'login';

        // Check 60-second cooldown
        $cooldown = $otpService->checkResendCooldown($primaryTarget, $purpose);
        if (! $cooldown['can_resend']) {
            return response()->json([
                'status' => false,
                'message' => "Please wait {$cooldown['cooldown_remaining_seconds']} seconds before requesting a new OTP.",
                'data' => [
                    'cooldown_remaining_seconds' => $cooldown['cooldown_remaining_seconds'],
                ],
            ], 429);
        }

        // Generate / retrieve active OTP
        $otpData = $otpService->getOrCreateOtp($primaryTarget, $purpose);
        $otpService->setResendCooldown($primaryTarget, $purpose);

        // Store OTP across all admin handles and phone variations
        if ($targetAdmin->email && $targetAdmin->email !== $primaryTarget) {
            $otpService->storeOtpDirectly($targetAdmin->email, $otpData['otp'], $purpose, 10);
        }
        if ($targetAdmin->username && $targetAdmin->username !== $primaryTarget) {
            $otpService->storeOtpDirectly($targetAdmin->username, $otpData['otp'], $purpose, 10);
        }
        if ($targetAdmin->phone_number) {
            foreach (\App\Actions\User\LoginUserAction::getPhoneVariations($targetAdmin->phone_number) as $phoneVar) {
                $otpService->storeOtpDirectly($phoneVar, $otpData['otp'], $purpose, 10);
            }
        }
        if ($inputPhone) {
            foreach (\App\Actions\User\LoginUserAction::getPhoneVariations($inputPhone) as $phoneVar) {
                $otpService->storeOtpDirectly($phoneVar, $otpData['otp'], $purpose, 10);
            }
        }

        // Dispatch email notification if email is present
        if (! empty($email)) {
            $adminName = $targetAdmin->full_name ?? $targetAdmin->name ?? 'Administrator';
            try {
                Notification::route('mail', $email)
                    ->notify(new AdminOtpNotification($otpData['otp'], $purpose, $adminName));
            } catch (\Throwable $e) {
                Log::warning('Could not dispatch Admin OTP email: ' . $e->getMessage());
            }
        }

        $responseData = [
            'identifier' => $identifier ?? $email ?? $phone,
            'expires_in_seconds' => $otpData['remaining_seconds'],
            'cooldown_seconds' => OtpService::DEFAULT_RESEND_COOLDOWN_SECONDS,
        ];

        if ($isMobileRequest || config('app.env') !== 'production') {
            $responseData['otp'] = $otpData['otp'];
        }

        $channelMsg = $isMobileRequest ? 'mobile number' : 'email address';

        return response()->json([
            'status' => true,
            'message' => "OTP has been sent to the administrator {$channelMsg}. Valid for 10 minutes.",
            'data' => $responseData,
        ], 200);
    }

    /**
     * Authenticate an administrator via OTP (alias for login with OTP).
     */
    public function loginWithOtp(LoginAdminRequest $request, LoginAdminAction $action): JsonResponse
    {
        return $this->login($request, $action);
    }


    /**
     * Send a password reset link to the administrator.
     */
    public function forgotPassword(ForgotPasswordAdminRequest $request, ForgotPasswordAdminAction $action): JsonResponse
    {
        $result = $action->execute($request->normalizedEmail());

        $response = [
            'status' => $result['status'],
            'message' => $result['message'],
        ];

        if (! empty($result['data'])) {
            $response['data'] = $result['data'];
        }

        return response()->json($response, $result['code'] ?? 200);
    }

    /**
     * Reset the administrator password using a valid reset token.
     */
    public function resetPassword(ResetPasswordAdminRequest $request, ResetPasswordAdminAction $action): JsonResponse
    {
        $result = $action->execute($request->credentials());

        if (! $result['success']) {
            return response()->json([
                'status' => false,
                'message' => $result['message'],
                'error' => $result['error'] ?? $result['message'],
            ], $result['code']);
        }

        $response = [
            'status' => true,
            'message' => $result['message'],
        ];

        if (! empty($result['data'])) {
            $response['data'] = $result['data'];
        }

        return response()->json($response, 200);
    }

    /**
     * Verify administrator password reset token validity.
     */
    public function verifyResetToken(
        VerifyResetTokenAdminRequest $request,
        VerifyResetTokenAdminAction $action
    ): JsonResponse {
        $result = $action->execute(
            (string) $request->input('email'),
            (string) $request->input('token')
        );

        if (! $result['success']) {
            return response()->json([
                'status' => false,
                'message' => $result['message'],
            ], $result['code']);
        }

        return response()->json([
            'status' => true,
            'message' => $result['message'],
        ], 200);
    }

    /**
     * Revoke the current device's personal access token.
     */
    public function logout(Request $request, LogoutAdminAction $action): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $response = $action->execute($admin);

        return response()->json($response, 200);
    }

    /**
     * Revoke all personal access tokens for the authenticated administrator.
     */
    public function logoutAll(Request $request, LogoutAllAdminSessionsAction $action): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $response = $action->execute($admin);

        return response()->json($response, 200);
    }
}
