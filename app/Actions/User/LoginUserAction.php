<?php

namespace App\Actions\User;

use App\Enums\NotificationType;
use App\Jobs\SendUserNotificationJob;
use App\Models\User;
use App\Services\OtpService;
use App\Services\UserActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LoginUserAction
{
    public function __construct(
        protected OtpService $otpService
    ) {}

    /**
     * Authenticate a user by username + password OR username + OTP and issue a Sanctum token.
     *
     * @param  array{username: string, password?: string|null, otp?: string|null, email_otp?: string|null, number_otp?: string|null}  $credentials
     * @return array{success: true, token: string, user: User}|array{success: false, message: string, code: int}
     */
    public function execute(array $credentials, string $deviceName = 'user-device'): array
    {
        $identifier = trim((string) $credentials['username']);
        $phoneVariations = self::getPhoneVariations($identifier);

        $user = User::query()
            ->select([
                'id',
                'first_name',
                'last_name',
                'phone_number',
                'name',
                'username',
                'email',
                'password',
                'status',
                'suspension_reason',
                'must_change_password',
            ])
            ->where('username', $identifier)
            ->orWhere('email', strtolower($identifier))
            ->orWhereIn('phone_number', $phoneVariations)
            ->first();

        if (! $user) {
            $notFoundMsg = 'No account found with this username.';
            if (str_contains($identifier, '@')) {
                $notFoundMsg = 'No account found with this email address.';
            } elseif (preg_match('/^\+?[0-9]{7,15}$/', $identifier)) {
                $notFoundMsg = 'No account found with this mobile number.';
            }

            return [
                'success' => false,
                'message' => $notFoundMsg,
                'code' => 401,
            ];
        }

        $otp = $credentials['otp'] ?? $credentials['email_otp'] ?? $credentials['number_otp'] ?? null;
        $password = $credentials['password'] ?? null;

        if (! empty($otp)) {
            // Verify OTP method
            $otpString = trim((string) $otp);
            $isValid = false;

            $candidateIdentifiers = [
                $user->email,
                $user->phone_number,
                $user->username,
                $identifier,
            ];

            if ($user->phone_number) {
                $candidateIdentifiers = array_merge($candidateIdentifiers, self::getPhoneVariations($user->phone_number));
            }
            $candidateIdentifiers = array_merge($candidateIdentifiers, $phoneVariations);
            $candidateIdentifiers = array_values(array_unique(array_filter($candidateIdentifiers)));

            $purposes = ['login', 'default', 'verification'];

            foreach ($candidateIdentifiers as $id) {
                foreach ($purposes as $purpose) {
                    if ($this->otpService->verify($id, $otpString, $purpose, consumeOnSuccess: true)) {
                        $isValid = true;
                        break 2;
                    }
                }
            }

            if (! $isValid) {
                UserActivityService::log(
                    $user,
                    'login_failed',
                    "Failed login attempt: invalid or expired OTP from device '{$deviceName}'",
                    ['device_name' => $deviceName, 'reason' => 'invalid_otp']
                );

                return [
                    'success' => false,
                    'message' => 'The provided OTP is invalid or has expired.',
                    'code' => 401,
                ];
            }
        } elseif (! empty($password)) {
            // Verify Password method
            if (! Hash::check((string) $password, $user->password)) {
                UserActivityService::log(
                    $user,
                    'login_failed',
                    "Failed login attempt: incorrect password from device '{$deviceName}'",
                    ['device_name' => $deviceName, 'reason' => 'incorrect_password']
                );

                return [
                    'success' => false,
                    'message' => 'Incorrect password.',
                    'code' => 401,
                ];
            }
        } else {
            return [
                'success' => false,
                'message' => 'Please provide either password or OTP to log in.',
                'code' => 422,
            ];
        }

        if ($user->status === 'suspended') {
            $message = ! empty($user->suspension_reason)
                ? "Account is suspended. Reason: {$user->suspension_reason}"
                : 'Account is suspended. Please contact administrator.';

            UserActivityService::log(
                $user,
                'login_failed',
                'Failed login attempt: account is suspended',
                ['device_name' => $deviceName, 'reason' => 'account_suspended']
            );

            return [
                'success' => false,
                'message' => $message,
                'code' => 403,
            ];
        }

        if ($user->status !== 'active') {
            UserActivityService::log(
                $user,
                'login_failed',
                'Failed login attempt: account is inactive',
                ['device_name' => $deviceName, 'reason' => 'account_inactive']
            );

            return [
                'success' => false,
                'message' => 'Account is inactive. Please contact administrator.',
                'code' => 403,
            ];
        }

        $loginMethod = ! empty($otp) ? 'otp' : 'password';

        UserActivityService::log(
            $user,
            'login',
            "User logged in via {$loginMethod} from device '{$deviceName}'",
            ['device_name' => $deviceName, 'method' => $loginMethod]
        );

        // Security Alert: New Login Notification
        SendUserNotificationJob::dispatch(
            $user->id,
            'Security Alert: New Login Detected',
            "Hello {{user_first_name}}, a new login was detected from device '{$deviceName}'.",
            NotificationType::PUSH_AND_IN_APP
        );

        // Revoke any previous tokens for this specific device to restart a fresh 24h session
        $user->tokens()->where('name', $deviceName)->delete();

        $expiresMinutes = (int) (config('sanctum.expiration') ?? 1440);
        $tokenResult = $user->createToken($deviceName, ['*'], now()->addMinutes($expiresMinutes));

        DB::table('personal_access_tokens')
            ->where('id', $tokenResult->accessToken->id)
            ->update([
                'plain_token' => $tokenResult->plainTextToken,
            ]);

        return [
            'success' => true,
            'token' => $tokenResult->plainTextToken,
            'user' => $user,
        ];
    }

    /**
     * Generate common phone number variations for robust matching (e.g. +91, 91, 0, or 10-digit).
     *
     * @return list<string>
     */
    public static function getPhoneVariations(string $phone): array
    {
        $trimmed = trim($phone);
        if ($trimmed === '') {
            return [];
        }

        $variations = [$trimmed];
        $digitsOnly = preg_replace('/\D/', '', $trimmed);

        if (strlen($digitsOnly) === 10) {
            $variations[] = '+91' . $digitsOnly;
            $variations[] = '91' . $digitsOnly;
            $variations[] = '0' . $digitsOnly;
            $variations[] = $digitsOnly;
        } elseif (strlen($digitsOnly) === 12 && str_starts_with($digitsOnly, '91')) {
            $tenDigit = substr($digitsOnly, 2);
            $variations[] = '+91' . $tenDigit;
            $variations[] = '91' . $tenDigit;
            $variations[] = '0' . $tenDigit;
            $variations[] = $tenDigit;
        } elseif (strlen($digitsOnly) === 11 && str_starts_with($digitsOnly, '0')) {
            $tenDigit = substr($digitsOnly, 1);
            $variations[] = '+91' . $tenDigit;
            $variations[] = '91' . $tenDigit;
            $variations[] = '0' . $tenDigit;
            $variations[] = $tenDigit;
        }

        return array_values(array_unique(array_filter($variations)));
    }
}
