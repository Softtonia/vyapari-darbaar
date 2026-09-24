<?php

namespace App\Http\Controllers\Api\User;

use App\Actions\User\ChangeUserPasswordAction;
use App\Actions\User\ForgotPasswordUserAction;
use App\Actions\User\LoginUserAction;
use App\Actions\User\LogoutUserAction;
use App\Actions\User\RefreshTokenAction;
use App\Actions\User\RegisterUserAction;
use App\Actions\User\ResetPasswordUserAction;
use App\Actions\User\VerifyResetTokenUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\ChangeUserPasswordRequest;
use App\Http\Requests\User\ForgotPasswordUserRequest;
use App\Http\Requests\User\LoginUserRequest;
use App\Http\Requests\User\RegisterUserRequest;
use App\Http\Requests\User\ResetPasswordUserRequest;
use App\Http\Requests\User\SendOtpRequest;
use App\Http\Requests\User\ValidateUsernameRequest;
use App\Http\Requests\User\VerifyOtpRequest;
use App\Http\Requests\User\VerifyResetTokenUserRequest;
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
        $purpose = (string) $request->input('purpose', 'registration');
        $inputEmail = $request->input('email');
        $inputUsername = $request->input('username');
        $inputPhone = $request->input('phone_number') ?? $request->input('mobile') ?? $request->input('phone') ?? $request->input('number');
        $rawIdentifier = $request->input('identifier') ?? $inputUsername ?? $inputEmail ?? $inputPhone;

        $targetUser = null;
        $identifier = null;

        if (! empty($rawIdentifier) || in_array($purpose, ['login', 'password_reset'], true)) {
            $identifier = trim((string) $rawIdentifier);
            $phoneVariations = LoginUserAction::getPhoneVariations($identifier);

            $targetUser = User::where('username', $identifier)
                ->orWhere('email', strtolower($identifier))
                ->orWhereIn('phone_number', $phoneVariations)
                ->first();

            if (! $targetUser && in_array($purpose, ['login', 'password_reset'], true)) {
                return response()->json([
                    'status' => false,
                    'message' => 'No account found with this identifier.',
                ], 404);
            }
        }

        $email = $targetUser ? $targetUser->email : ($inputEmail ? strtolower(trim((string) $inputEmail)) : null);
        $phone = $targetUser ? $targetUser->phone_number : $inputPhone;
        $isMobileRequest = ! empty($inputPhone) && (empty($inputEmail) || $rawIdentifier === $inputPhone);
        $primaryTarget = $email ?? $phone ?? $identifier;

        if (empty($primaryTarget)) {
            return response()->json([
                'status' => false,
                'message' => 'No email address or mobile number found to deliver OTP.',
            ], 422);
        }

        // Check 60-second cooldown on primary target
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

        // Store the identical OTP across all associated identifiers (email, username, phone variations)
        if ($targetUser) {
            if ($targetUser->email && $targetUser->email !== $primaryTarget) {
                $otpService->storeOtpDirectly($targetUser->email, $otpData['otp'], $purpose, 10);
            }
            if ($targetUser->username && $targetUser->username !== $primaryTarget) {
                $otpService->storeOtpDirectly($targetUser->username, $otpData['otp'], $purpose, 10);
            }
            if ($targetUser->phone_number) {
                foreach (LoginUserAction::getPhoneVariations($targetUser->phone_number) as $phoneVar) {
                    $otpService->storeOtpDirectly($phoneVar, $otpData['otp'], $purpose, 10);
                }
            }
        }

        if ($identifier && $identifier !== $primaryTarget) {
            $otpService->storeOtpDirectly($identifier, $otpData['otp'], $purpose, 10);
        }

        if ($inputPhone) {
            foreach (LoginUserAction::getPhoneVariations($inputPhone) as $phoneVar) {
                $otpService->storeOtpDirectly($phoneVar, $otpData['otp'], $purpose, 10);
            }
        }

        // Dispatch queued email notification if email is present
        if (! empty($email)) {
            $recipientName = $targetUser ? ($targetUser->full_name ?? $targetUser->name) : 'User';
            try {
                Notification::route('mail', $email)
                    ->notify(new UserOtpNotification($otpData['otp'], $purpose, $recipientName));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Could not dispatch OTP email: ' . $e->getMessage());
            }
        }

        $responseData = [
            'identifier' => $identifier ?? $email ?? $phone,
            'expires_in_seconds' => $otpData['remaining_seconds'],
            'cooldown_seconds' => OtpService::DEFAULT_RESEND_COOLDOWN_SECONDS,
        ];

        // Return OTP in response for mobile or non-production environment
        if ($isMobileRequest || config('app.env') !== 'production') {
            $responseData['otp'] = $otpData['otp'];
        }

        $channelMsg = $isMobileRequest ? 'mobile number' : 'email address';

        return response()->json([
            'status' => true,
            'message' => "OTP has been sent to the {$channelMsg}. Valid for 10 minutes.",
            'data' => $responseData,
        ], 200);
    }

    /**
     * Send an OTP specifically for user login.
     */
    public function sendLoginOtp(SendOtpRequest $request, OtpService $otpService): JsonResponse
    {
        $request->merge(['purpose' => 'login']);

        return $this->sendOtp($request, $otpService);
    }

    /**
     * Authenticate a user via OTP (alias for login with OTP).
     */
    public function loginWithOtp(LoginUserRequest $request, LoginUserAction $action): JsonResponse
    {
        return $this->login($request, $action);
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
        $deviceName = $this->parseUserAgent($request->header('User-Agent'), $request->ip());

        $result = $action->execute($request->validated(), (string) $deviceName);

        /** @var User|null $user */
        $user = $result['user'] ?? null;
        $roleName = $user?->roles?->first()?->name ?? 'user';

        if ($user) {
            app(\App\Services\CampaignEmailService::class)->triggerEvent(
                \App\Enums\CampaignEvent::REGISTER,
                $user,
                ['name' => $user->name, 'email' => $user->email, 'phone' => $user->phone]
            );

            app(\App\Services\CampaignEmailService::class)->triggerEvent(
                \App\Enums\CampaignEvent::WELCOME_USER,
                $user,
                ['name' => $user->name, 'email' => $user->email, 'phone' => $user->phone]
            );
        }

        return response()->json([
            'status' => true,
            'message' => 'User registered successfully.',
            'data' => [
                'token' => $result['token'],
                'role' => $roleName,
            ],
        ], 201);
    }

    /**
     * Authenticate a user by username and issue a Sanctum token.
     */
    public function login(LoginUserRequest $request, LoginUserAction $action): JsonResponse
    {
        $deviceName = $this->parseUserAgent($request->header('User-Agent'), $request->ip());

        $result = $action->execute($request->credentials(), (string) $deviceName);

        if (! $result['success']) {
            return response()->json([
                'status' => false,
                'message' => $result['message'],
            ], $result['code']);
        }

        /** @var User $user */
        $user = $result['user'];
        $user->loadMissing('roles');
        $roleName = $user->roles->first()?->name ?? 'user';

        return response()->json([
            'status' => true,
            'message' => 'Login successful.',
            'data' => [
                'token' => $result['token'],
                'role' => $roleName,
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
        
        $deviceName = $this->parseUserAgent($request->header('User-Agent'), $request->ip());

        $result = $action->execute($user, (string) $deviceName);

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

        if ($result['status']) {
            $user = \App\Models\User::where('email', $request->email())->first();
            if ($user) {
                app(\App\Services\CampaignEmailService::class)->triggerEvent(
                    \App\Enums\CampaignEvent::FORGET_PASSWORD,
                    $user,
                    ['name' => $user->name, 'email' => $user->email, 'phone' => $user->phone]
                );
            }
        }

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
     * Verify user password reset token validity.
     */
    public function verifyResetToken(
        VerifyResetTokenUserRequest $request,
        VerifyResetTokenUserAction $action
    ): JsonResponse {
        $result = $action->execute(
            (string) $request->input('email'),
            (string) $request->input('token')
        );

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

        app(\App\Services\CampaignEmailService::class)->triggerEvent(
            \App\Enums\CampaignEvent::CHANGE_PASSWORD,
            $user,
            ['name' => $user->name, 'email' => $user->email, 'phone' => $user->phone]
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
        $fcmToken = $request->input('fcm_token');

        $action->execute($user, is_string($fcmToken) ? $fcmToken : null);

        return response()->json([
            'status' => true,
            'message' => 'Logged out successfully.',
        ], 200);
    }

    /**
     * Validate username availability and format in real-time.
     */
    public function validateUsername(ValidateUsernameRequest $request): JsonResponse
    {
        $username = trim((string) $request->input('username'));

        return response()->json([
            'status' => true,
            'message' => "The username '{$username}' is available.",
            'data' => [
                'username' => $username,
                'is_available' => true,
            ],
        ], 200);
    }

    /**
     * Parse User-Agent into a readable device name (OS + Browser) and append IP.
     */
    private function parseUserAgent(?string $userAgent, ?string $ip = null): string
    {
        if (empty($userAgent)) {
            return 'Unknown Device' . ($ip ? " (IP: $ip)" : '');
        }

        $os = 'Unknown OS';
        if (preg_match('/windows|win32/i', $userAgent)) $os = 'Windows';
        elseif (preg_match('/macintosh|mac os x/i', $userAgent)) $os = 'Mac';
        elseif (preg_match('/linux/i', $userAgent)) $os = 'Linux';
        elseif (preg_match('/iphone|ipad|ipod/i', $userAgent)) $os = 'iOS';
        elseif (preg_match('/android/i', $userAgent)) $os = 'Android';

        $browser = 'Unknown Browser';
        if (preg_match('/edg/i', $userAgent)) $browser = 'Edge';
        elseif (preg_match('/opr|opera/i', $userAgent)) $browser = 'Opera';
        elseif (preg_match('/chrome/i', $userAgent)) $browser = 'Chrome';
        elseif (preg_match('/safari/i', $userAgent)) $browser = 'Safari';
        elseif (preg_match('/firefox/i', $userAgent)) $browser = 'Firefox';
        elseif (preg_match('/postman/i', $userAgent)) $browser = 'Postman';

        $parsed = trim("$os - $browser", ' -');
        return $ip ? "$parsed (IP: $ip)" : $parsed;
    }
}
