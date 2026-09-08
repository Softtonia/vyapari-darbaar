<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class OtpService
{
    public const DEFAULT_EXPIRY_MINUTES = 10;
    public const DEFAULT_RESEND_COOLDOWN_SECONDS = 60;
    public const DEFAULT_OTP_LENGTH = 6;

    /**
     * Get the active OTP if still valid within the 10-minute window,
     * or generate a new one if expired / not found.
     *
     * @param  string  $identifier  (e.g., email or phone number)
     * @param  string  $purpose     (e.g., 'password_reset', 'email_verification', 'login')
     * @param  int  $expiryMinutes  Default is 10 minutes
     * @param  int  $length         Default is 6 digits
     * @return array{otp: string, is_new: bool, expires_at: Carbon, remaining_seconds: int}
     */
    public function getOrCreateOtp(
        string $identifier,
        string $purpose = 'default',
        int $expiryMinutes = self::DEFAULT_EXPIRY_MINUTES,
        int $length = self::DEFAULT_OTP_LENGTH
    ): array {
        $cacheKey = $this->buildCacheKey($identifier, $purpose);
        $cachedData = Cache::get($cacheKey);

        $now = Carbon::now();

        // 1. If an OTP exists and is still valid within the 10-minute window, reuse it
        if (is_array($cachedData) && isset($cachedData['otp'], $cachedData['expires_at'])) {
            $expiresAt = Carbon::parse($cachedData['expires_at']);

            if ($expiresAt->isAfter($now)) {
                $remainingSeconds = max(0, $now->diffInSeconds($expiresAt, false));

                return [
                    'otp' => (string) $cachedData['otp'],
                    'is_new' => false,
                    'expires_at' => $expiresAt,
                    'remaining_seconds' => (int) $remainingSeconds,
                ];
            }
        }

        // 2. Generate a new OTP if none exists or previous has expired
        $otp = $this->generateNumericCode($length);
        $expiresAt = $now->copy()->addMinutes($expiryMinutes);

        $dataToStore = [
            'otp' => $otp,
            'created_at' => $now->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
        ];

        // Store in cache for the full expiry duration
        Cache::put($cacheKey, $dataToStore, $expiresAt);

        return [
            'otp' => $otp,
            'is_new' => true,
            'expires_at' => $expiresAt,
            'remaining_seconds' => $expiryMinutes * 60,
        ];
    }

    /**
     * Check if a resend cooldown is currently active.
     *
     * @param  string  $identifier
     * @param  string  $purpose
     * @param  int  $cooldownSeconds
     * @return array{can_resend: bool, cooldown_remaining_seconds: int}
     */
    public function checkResendCooldown(
        string $identifier,
        string $purpose = 'default',
        int $cooldownSeconds = self::DEFAULT_RESEND_COOLDOWN_SECONDS
    ): array {
        $cooldownKey = $this->buildCooldownKey($identifier, $purpose);
        $cooldownUntil = Cache::get($cooldownKey);

        if ($cooldownUntil) {
            $cooldownTime = Carbon::parse($cooldownUntil);
            $now = Carbon::now();

            if ($cooldownTime->isAfter($now)) {
                return [
                    'can_resend' => false,
                    'cooldown_remaining_seconds' => (int) $now->diffInSeconds($cooldownTime, false),
                ];
            }
        }

        return [
            'can_resend' => true,
            'cooldown_remaining_seconds' => 0,
        ];
    }

    /**
     * Set resend cooldown timestamp.
     */
    public function setResendCooldown(
        string $identifier,
        string $purpose = 'default',
        int $cooldownSeconds = self::DEFAULT_RESEND_COOLDOWN_SECONDS
    ): void {
        $cooldownKey = $this->buildCooldownKey($identifier, $purpose);
        $cooldownUntil = Carbon::now()->addSeconds($cooldownSeconds);

        Cache::put($cooldownKey, $cooldownUntil->toIso8601String(), $cooldownUntil);
    }

    /**
     * Verify the provided OTP against the active OTP.
     *
     * @param  string  $identifier
     * @param  string  $inputOtp
     * @param  string  $purpose
     * @param  bool  $consumeOnSuccess  If true, OTP is destroyed upon successful verification
     * @return bool
     */
    public function verify(
        string $identifier,
        string $inputOtp,
        string $purpose = 'default',
        bool $consumeOnSuccess = true
    ): bool {
        $cacheKey = $this->buildCacheKey($identifier, $purpose);
        $cachedData = Cache::get($cacheKey);

        if (!is_array($cachedData) || !isset($cachedData['otp'], $cachedData['expires_at'])) {
            return false;
        }

        $expiresAt = Carbon::parse($cachedData['expires_at']);
        if (Carbon::now()->isAfter($expiresAt)) {
            // Expired
            $this->invalidate($identifier, $purpose);
            return false;
        }

        // Constant time comparison
        if (!hash_equals((string) $cachedData['otp'], trim($inputOtp))) {
            return false;
        }

        if ($consumeOnSuccess) {
            $this->invalidate($identifier, $purpose);
        }

        return true;
    }

    /**
     * Invalidate/delete the OTP and cooldown.
     */
    public function invalidate(string $identifier, string $purpose = 'default'): void
    {
        Cache::forget($this->buildCacheKey($identifier, $purpose));
        Cache::forget($this->buildCooldownKey($identifier, $purpose));
    }

    /**
     * Get remaining validity seconds for active OTP.
     */
    public function getRemainingSeconds(string $identifier, string $purpose = 'default'): int
    {
        $cacheKey = $this->buildCacheKey($identifier, $purpose);
        $cachedData = Cache::get($cacheKey);

        if (is_array($cachedData) && isset($cachedData['expires_at'])) {
            $expiresAt = Carbon::parse($cachedData['expires_at']);
            $now = Carbon::now();

            if ($expiresAt->isAfter($now)) {
                return (int) $now->diffInSeconds($expiresAt, false);
            }
        }

        return 0;
    }

    /**
     * Build cache key for OTP.
     */
    protected function buildCacheKey(string $identifier, string $purpose): string
    {
        $normalized = strtolower(trim($identifier));
        return "otp:{$purpose}:{$normalized}";
    }

    /**
     * Build cache key for resend cooldown.
     */
    protected function buildCooldownKey(string $identifier, string $purpose): string
    {
        $normalized = strtolower(trim($identifier));
        return "otp_cooldown:{$purpose}:{$normalized}";
    }

    /**
     * Generate secure random numeric OTP string.
     */
    protected function generateNumericCode(int $length): string
    {
        $min = (int) ('1'.str_repeat('0', $length - 1));
        $max = (int) str_repeat('9', $length);

        return (string) random_int($min, $max);
    }
}
