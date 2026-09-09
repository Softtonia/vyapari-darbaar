<?php

namespace App\Actions\User;

use App\Models\Role;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegisterUserAction
{
    public function __construct(
        protected OtpService $otpService
    ) {}

    /**
     * Register a new user after verifying OTP.
     *
     * @param  array<string, mixed>  $data
     * @param  string  $deviceName
     * @return array{token: string, user: User}
     *
     * @throws ValidationException
     */
    public function execute(array $data, string $deviceName = 'user-device'): array
    {
        $email = strtolower(trim((string) $data['email']));
        $otp = (string) $data['otp'];

        // 1. Verify and consume OTP for registration
        $isValid = $this->otpService->verify($email, $otp, 'registration', consumeOnSuccess: true)
            || $this->otpService->verify($email, $otp, 'register', consumeOnSuccess: true);

        if (! $isValid) {
            throw ValidationException::withMessages([
                'otp' => ['The provided OTP is invalid or has expired.'],
            ]);
        }

        // 2. Resolve username if not supplied
        $username = isset($data['username']) && ! empty($data['username'])
            ? trim((string) $data['username'])
            : $this->generateUniqueUsername($email, (string) $data['first_name']);

        // 3. Resolve role (default 'user')
        $requestedRole = isset($data['role']) && ! empty($data['role'])
            ? strtolower(trim((string) $data['role']))
            : 'user';

        $allowedRoles = ['user', 'trader', 'subscriber', 'advertiser', 'guest'];
        $roleName = in_array($requestedRole, $allowedRoles, true) ? $requestedRole : 'user';

        // 4. Create User and issue token in transaction
        return DB::transaction(function () use ($data, $email, $username, $roleName, $deviceName) {
            $firstName = trim((string) $data['first_name']);
            $lastName = isset($data['last_name']) ? trim((string) $data['last_name']) : null;
            $fullName = trim($firstName.' '.($lastName ?? ''));

            /** @var User $user */
            $user = User::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'name' => $fullName,
                'email' => $email,
                'phone_number' => isset($data['phone_number']) ? trim((string) $data['phone_number']) : null,
                'username' => $username,
                'password' => Hash::make((string) $data['password']),
                'status' => 'active',
                'must_change_password' => false,
            ]);

            // Assign Spatie role
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $user->assignRole($role);
            }

            // Create Company & Association for Trader role only
            if ($roleName === 'trader') {
                $companyName = trim((string) ($data['company_name'] ?? $data['company']['name'] ?? ($fullName.' Enterprise')));
                $commodities = $data['commodities_handled'] ?? [];
                if (is_string($commodities)) {
                    $decoded = json_decode($commodities, true);
                    $commodities = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $commodities)));
                }

                $company = \App\Models\Company::create([
                    'name' => $companyName,
                    'contact_person' => trim((string) ($data['contact_person'] ?? $fullName)),
                    'business_type' => isset($data['business_type']) ? trim((string) $data['business_type']) : null,
                    'gstin' => isset($data['gstin']) ? strtoupper(trim((string) $data['gstin'])) : null,
                    'country' => isset($data['country']) ? trim((string) $data['country']) : 'India',
                    'state' => isset($data['state']) ? trim((string) $data['state']) : null,
                    'city' => isset($data['city']) ? trim((string) $data['city']) : null,
                    'address' => isset($data['address']) ? trim((string) $data['address']) : null,
                    'commodities_handled' => $commodities,
                    'trade_preference' => strtolower((string) ($data['trade_preference'] ?? $data['buy_sell_preference'] ?? 'both')),
                    'verification_status' => 'pending',
                ]);

                $user->companies()->attach($company->id, [
                    'role' => 'owner',
                    'is_primary' => true,
                ]);
            }

            // Issue Sanctum token
            $expiresMinutes = (int) (config('sanctum.expiration') ?? 1440);
            $tokenResult = $user->createToken($deviceName, ['*'], now()->addMinutes($expiresMinutes));

            DB::table('personal_access_tokens')
                ->where('id', $tokenResult->accessToken->id)
                ->update([
                    'plain_token' => $tokenResult->plainTextToken,
                ]);

            \App\Services\UserActivityService::log(
                $user,
                'register',
                'User registered new account',
                ['role' => $roleName, 'device_name' => $deviceName]
            );

            return [
                'token' => $tokenResult->plainTextToken,
                'user' => $user->fresh(['roles']),
            ];
        });
    }

    /**
     * Generate a unique username from email or name.
     */
    protected function generateUniqueUsername(string $email, string $firstName): string
    {
        $base = Str::slug(explode('@', $email)[0] ?: $firstName);
        if (empty($base)) {
            $base = 'user';
        }

        $username = $base;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $base.random_int(100, 9999);
            $counter++;
            if ($counter > 20) {
                $username = $base.'_'.Str::random(6);
                break;
            }
        }

        return $username;
    }
}
