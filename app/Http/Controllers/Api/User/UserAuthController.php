<?php

namespace App\Http\Controllers\Api\User;

use App\Actions\User\ChangeUserPasswordAction;
use App\Actions\User\ForgotPasswordUserAction;
use App\Actions\User\LoginUserAction;
use App\Actions\User\LogoutUserAction;
use App\Actions\User\RefreshTokenAction;
use App\Actions\User\RegisterUserAction;
use App\Actions\User\ResetPasswordUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\ChangeUserPasswordRequest;
use App\Http\Requests\User\ForgotPasswordUserRequest;
use App\Http\Requests\User\LoginUserRequest;
use App\Http\Requests\User\RegisterUserRequest;
use App\Http\Requests\User\ResetPasswordUserRequest;
use App\Http\Requests\User\SendOtpRequest;
use App\Http\Requests\User\VerifyOtpRequest;
use App\Http\Resources\UserProfileResource;
use App\Models\User;
use App\Notifications\UserOtpNotification;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class UserAuthController extends Controller
{
    /**
     * Send an OTP to the given email address.
     */
    public function sendOtp(SendOtpRequest $request, OtpService $otpService): JsonResponse
    {
        $email = strtolower(trim((string) $request->input('email')));
        $purpose = (string) $request->input('purpose', 'registration');

        // Check 60-second cooldown
        $cooldown = $otpService->checkResendCooldown($email, $purpose);
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
        $otpData = $otpService->getOrCreateOtp($email, $purpose);
        $otpService->setResendCooldown($email, $purpose);

        // Dispatch queued email notification
        Notification::route('mail', $email)
            ->notify(new UserOtpNotification($otpData['otp'], $purpose));

        return response()->json([
            'status' => true,
            'message' => 'OTP has been sent to the email address. Valid for 10 minutes.',
            'data' => [
                'expires_in_seconds' => $otpData['remaining_seconds'],
                'cooldown_seconds' => OtpService::DEFAULT_RESEND_COOLDOWN_SECONDS,
            ],
        ], 200);
    }

    /**
     * Standalone verification of OTP.
     */
    public function verifyOtp(VerifyOtpRequest $request, OtpService $otpService): JsonResponse
    {
        $email = strtolower(trim((string) $request->input('email')));
        $otp = (string) $request->input('otp');
        $purpose = (string) $request->input('purpose', 'registration');

        $isValid = $otpService->verify($email, $otp, $purpose, consumeOnSuccess: false);

        if (! $isValid) {
            return response()->json([
                'status' => false,
                'message' => 'The provided OTP is invalid or has expired.',
                'errors' => [
                    'otp' => ['The provided OTP is invalid or has expired.'],
                ],
            ], 422);
        }

        return response()->json([
            'status' => true,
            'message' => 'OTP verified successfully.',
            'data' => [
                'email' => $email,
                'verified' => true,
            ],
        ], 200);
    }

    /**
     * Register a new user with OTP verification.
     */
    public function register(RegisterUserRequest $request, RegisterUserAction $action): JsonResponse
    {
        $deviceName = (string) $request->input('device_name', $request->header('User-Agent', 'user-device'));
        $result = $action->execute($request->validated(), $deviceName);

        return response()->json([
            'status' => true,
            'message' => 'User registered successfully.',
            'data' => [
                'token' => $result['token'],
                'token_type' => 'Bearer',
                'user' => new UserProfileResource($result['user']),
            ],
        ], 201);
    }

    /**
     * Authenticate a user by username and issue a Sanctum token.
     */
    public function login(LoginUserRequest $request, LoginUserAction $action): JsonResponse
    {
        $deviceName = (string) $request->input('device_name', $request->header('User-Agent', 'user-device'));
        $result = $action->execute($request->credentials(), $deviceName);

        if (! $result['success']) {
            return response()->json([
                'status' => false,
                'message' => $result['message'],
            ], $result['code']);
        }

        return response()->json([
            'status' => true,
            'message' => 'Login successful.',
            'data' => [
                'token' => $result['token'],
            ],
        ], 200);
    }

    /**
     * Refresh the authenticated user's access token.
     */
    public function refreshToken(Request $request, RefreshTokenAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $deviceName = (string) $request->input('device_name', $request->header('User-Agent', 'user-device'));

        $result = $action->execute($user, $deviceName);

        return response()->json([
            'status' => true,
            'message' => 'Token refreshed successfully.',
            'data' => [
                'token' => $result['token'],
                'token_type' => $result['token_type'],
            ],
        ], 200);
    }

    /**
     * Send a password reset link to the given user email address.
     */
    public function forgotPassword(
        ForgotPasswordUserRequest $request,
        ForgotPasswordUserAction $action
    ): JsonResponse {
        $result = $action->execute($request->email());

        return response()->json([
            'status' => $result['status'],
            'message' => $result['message'],
        ], $result['code']);
    }

    /**
     * Reset user password using the provided token.
     */
    public function resetPassword(
        ResetPasswordUserRequest $request,
        ResetPasswordUserAction $action
    ): JsonResponse {
        $result = $action->execute($request->credentials());

        return response()->json([
            'status' => $result['success'],
            'message' => $result['message'],
        ], $result['code']);
    }

    /**
     * Change the authenticated user's password and revoke other active sessions.
     */
    public function changePassword(
        ChangeUserPasswordRequest $request,
        ChangeUserPasswordAction $action
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $action->execute(
            $user,
            (string) $request->input('current_password'),
            (string) $request->input('password')
        );

        return response()->json([
            'status' => true,
            'message' => 'Password changed successfully.',
        ], 200);
    }

    /**
     * Log out the current device session.
     */
    public function logout(Request $request, LogoutUserAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $action->execute($user);

        return response()->json([
            'status' => true,
            'message' => 'Logged out successfully.',
        ], 200);
    }
}
