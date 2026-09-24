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
        $identifier = $this->normalizeIdentifier($identifier);
        $now = Carbon::now();

        // 1. If an OTP exists and is still valid within the 10-minute window, reuse it
        $existingOtp = \App\Models\Otp::where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->where('expires_at', '>', $now)
            ->latest()
            ->first();

        if ($existingOtp) {
            $remainingSeconds = max(0, $now->diffInSeconds($existingOtp->expires_at, false));

            return [
                'otp' => $existingOtp->otp,
                'is_new' => false,
                'expires_at' => $existingOtp->expires_at,
                'remaining_seconds' => (int) $remainingSeconds,
            ];
        }

        // 2. Generate a new OTP if none exists or previous has expired
        $otp = $this->generateNumericCode($length);
        $expiresAt = $now->copy()->addMinutes($expiryMinutes);

        \App\Models\Otp::create([
            'identifier' => $identifier,
            'purpose' => $purpose,
            'otp' => $otp,
            'expires_at' => $expiresAt,
        ]);

        return [
            'otp' => $otp,
            'is_new' => true,
            'expires_at' => $expiresAt,
            'remaining_seconds' => $expiryMinutes * 60,
        ];
    }

    /**
     * Store a specific OTP directly against an identifier.
     */
    public function storeOtpDirectly(
        string $identifier,
        string $otp,
        string $purpose = 'default',
        int $expiryMinutes = self::DEFAULT_EXPIRY_MINUTES
    ): void {
        $identifier = $this->normalizeIdentifier($identifier);
        $now = Carbon::now();
        $expiresAt = $now->copy()->addMinutes($expiryMinutes);

        \App\Models\Otp::create([
            'identifier' => $identifier,
            'purpose' => $purpose,
            'otp' => $otp,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Check if a resend cooldown is currently active.
     * We check if the last generated OTP was within the cooldown period.
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
        $identifier = $this->normalizeIdentifier($identifier);
        
        $lastOtp = \App\Models\Otp::where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->latest()
            ->first();

        if ($lastOtp) {
            $now = Carbon::now();
            $cooldownTime = $lastOtp->created_at->addSeconds($cooldownSeconds);

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
     * In the DB approach, the cooldown is implicitly set by the created_at timestamp
     * of the OTP itself. We only keep this method for backward compatibility.
     */
    public function setResendCooldown(
        string $identifier,
        string $purpose = 'default',
        int $cooldownSeconds = self::DEFAULT_RESEND_COOLDOWN_SECONDS
    ): void {
        // No-op for database-backed OTPs as created_at handles this naturally,
        // unless you specifically need to force a cooldown without creating an OTP.
    }

    /**
     * Verify the provided OTP against the active OTP.
     *
     * @param  string  $identifier
     * @param  string  $inputOtp
     * @param  string  $purpose
     * @param  bool  $consumeOnSuccess  If true, OTP is destroyed/marked verified upon successful verification
     * @return bool
     */
    public function verify(
        string $identifier,
        string $inputOtp,
        string $purpose = 'default',
        bool $consumeOnSuccess = true
    ): bool {
        $identifier = $this->normalizeIdentifier($identifier);
        $now = Carbon::now();

        $activeOtp = \App\Models\Otp::where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->where('expires_at', '>', $now)
            ->latest()
            ->first();

        if (!$activeOtp) {
            return false;
        }

        // Constant time comparison
        if (!hash_equals($activeOtp->otp, trim($inputOtp))) {
            return false;
        }

        if ($consumeOnSuccess) {
            $activeOtp->update(['verified_at' => $now]);
        }

        return true;
    }

    /**
     * Invalidate/delete the OTP.
     */
    public function invalidate(string $identifier, string $purpose = 'default'): void
    {
        $identifier = $this->normalizeIdentifier($identifier);
        
        \App\Models\Otp::where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->update(['verified_at' => Carbon::now()]);
    }

    /**
     * Get remaining validity seconds for active OTP.
     */
    public function getRemainingSeconds(string $identifier, string $purpose = 'default'): int
    {
        $identifier = $this->normalizeIdentifier($identifier);
        $now = Carbon::now();

        $activeOtp = \App\Models\Otp::where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->where('expires_at', '>', $now)
            ->latest()
            ->first();

        if ($activeOtp) {
            return (int) $now->diffInSeconds($activeOtp->expires_at, false);
        }

        return 0;
    }

    /**
     * Normalize identifier for DB storage.
     */
    protected function normalizeIdentifier(string $identifier): string
    {
        return strtolower(trim($identifier));
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
