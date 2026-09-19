<?php

namespace App\Actions\Admin;

use App\Actions\User\LoginUserAction;
use App\Models\User;
use App\Services\OtpService;
use App\Services\UserActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LoginAdminAction
{
    /**
     * Execute the admin login action (supports password and OTP for email, mobile, and username).
     *
     * @param  array<string, mixed>  $credentials
     * @return array{success: bool, message: string, data?: array<string, mixed>, code: int}
     */
    public function execute(array $credentials, string $tokenName = 'admin-token'): array
    {
        $rawIdentifier = $credentials['identifier']
            ?? $credentials['email']
            ?? $credentials['username']
            ?? $credentials['phone_number']
            ?? $credentials['mobile']
            ?? $credentials['phone']
            ?? null;

        if (empty($rawIdentifier)) {
            return [
                'success' => false,
                'message' => 'Please provide an administrator email, username, or mobile number.',
                'code' => 422,
            ];
        }

        $identifier = trim((string) $rawIdentifier);
        $phoneVariations = LoginUserAction::getPhoneVariations($identifier);

        $user = User::query()
            ->with('roles')
            ->where(function ($query) use ($identifier, $phoneVariations) {
                $query->where('email', strtolower($identifier))
                    ->orWhere('username', $identifier);
                if (! empty($phoneVariations)) {
                    $query->orWhereIn('phone_number', $phoneVariations);
                }
            })
            ->first();

        if (! $user) {
            $isEmail = str_contains($identifier, '@');
            $message = $isEmail ? 'No account found with this email address.' : 'No administrative account found with these credentials.';

            return [
                'success' => false,
                'message' => $message,
                'code' => 401,
            ];
        }

        $otp = $credentials['otp'] ?? $credentials['code'] ?? $credentials['admin_otp'] ?? $credentials['mobile_otp'] ?? null;
        $password = $credentials['password'] ?? null;

        if (! empty($otp)) {
            // Verify OTP
            $otpString = trim((string) $otp);
            $isValid = false;

            $candidateIdentifiers = [
                $user->email,
                $user->phone_number,
                $user->username,
                $identifier,
            ];

            if ($user->phone_number) {
                $candidateIdentifiers = array_merge($candidateIdentifiers, LoginUserAction::getPhoneVariations($user->phone_number));
            }
            $candidateIdentifiers = array_merge($candidateIdentifiers, $phoneVariations);
            $candidateIdentifiers = array_values(array_unique(array_filter($candidateIdentifiers)));

            $purposes = ['login', 'admin_login', 'default'];
            /** @var OtpService $otpService */
            $otpService = app(OtpService::class);

            foreach ($candidateIdentifiers as $id) {
                foreach ($purposes as $purpose) {
                    if ($otpService->verify($id, $otpString, $purpose, consumeOnSuccess: true)) {
                        $isValid = true;
                        break 2;
                    }
                }
            }

            if (! $isValid) {
                UserActivityService::log(
                    $user,
                    'login_failed',
                    "Failed admin login attempt: invalid or expired OTP from device '{$tokenName}'",
                    ['device_name' => $tokenName, 'reason' => 'invalid_otp']
                );

                return [
                    'success' => false,
                    'message' => 'The provided OTP is invalid or has expired.',
                    'code' => 401,
                ];
            }
        } elseif (! empty($password)) {
            // Verify Password
            if (! Hash::check((string) $password, $user->password)) {
                UserActivityService::log(
                    $user,
                    'login_failed',
                    "Failed admin login attempt: incorrect password from device '{$tokenName}'",
                    ['device_name' => $tokenName, 'reason' => 'incorrect_password']
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

        // Verify administrative privileges
        $hasAccess = (bool) $user->is_default
            || $user->hasAnyRole(['super_admin', 'admin', 'editor'])
            || $user->getAllPermissions()->isNotEmpty();

        if (! $hasAccess) {
            UserActivityService::log(
                $user,
                'login_failed',
                "Failed admin login attempt: unauthorized administrative access from device '{$tokenName}'",
                ['device_name' => $tokenName, 'reason' => 'unauthorized_access']
            );

            return [
                'success' => false,
                'message' => 'Unauthorized access.',
                'code' => 403,
            ];
        }

        if ($user->status !== 'active') {
            UserActivityService::log(
                $user,
                'login_failed',
                "Failed admin login attempt: account inactive from device '{$tokenName}'",
                ['device_name' => $tokenName, 'reason' => 'account_inactive']
            );

            return [
                'success' => false,
                'message' => 'Account is inactive. Please contact the administrator.',
                'code' => 403,
            ];
        }

        $user->update([
            'last_login_at' => now(),
        ]);

        $roleName = $user->roles->first()?->name ?? ($user->is_default ? 'super_admin' : 'admin');

        UserActivityService::log(
            $user,
            'login',
            "Admin logged in from device '{$tokenName}'",
            ['device_name' => $tokenName, 'role' => $roleName]
        );

        // Check if an unexpired active token exists for this admin
        $activeToken = DB::table('personal_access_tokens')
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->where('name', $tokenName)
            ->where('expires_at', '>', now())
            ->whereNotNull('plain_token')
            ->latest('id')
            ->first();

        if ($activeToken && ! empty($activeToken->plain_token)) {
            return [
                'success' => true,
                'message' => 'Login successful.',
                'code' => 200,
                'data' => [
                    'token' => $activeToken->plain_token,
                    'role' => $roleName,
                ],
            ];
        }

        $expiresMinutes = (int) (config('sanctum.expiration') ?? 1440);
        $tokenResult = $user->createToken($tokenName, ['*'], now()->addMinutes($expiresMinutes));

        DB::table('personal_access_tokens')
            ->where('id', $tokenResult->accessToken->id)
            ->update([
                'plain_token' => $tokenResult->plainTextToken,
            ]);

        return [
            'success' => true,
            'message' => 'Login successful.',
            'code' => 200,
            'data' => [
                'token' => $tokenResult->plainTextToken,
                'role' => $roleName,
            ],
        ];
    }
}
