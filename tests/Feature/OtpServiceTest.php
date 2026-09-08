<?php

namespace Tests\Feature;

use App\Services\OtpService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    use RefreshDatabase;

    protected OtpService $otpService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->otpService = app(OtpService::class);
        Cache::flush();
    }

    public function test_it_generates_new_otp_with_10_minute_validity(): void
    {
        $email = 'user@example.com';
        $result = $this->otpService->getOrCreateOtp($email, 'login');

        $this->assertTrue($result['is_new']);
        $this->assertEquals(6, strlen($result['otp']));
        $this->assertGreaterThan(590, $result['remaining_seconds']);
        $this->assertLessThanOrEqual(600, $result['remaining_seconds']);
    }

    public function test_it_reuses_same_otp_when_requested_again_within_10_minutes(): void
    {
        $email = 'user@example.com';

        // First request
        $first = $this->otpService->getOrCreateOtp($email, 'password_reset');
        $firstOtp = $first['otp'];
        $this->assertTrue($first['is_new']);

        // 3 minutes pass
        Carbon::setTestNow(now()->addMinutes(3));

        // Second request (Resend)
        $second = $this->otpService->getOrCreateOtp($email, 'password_reset');

        // Must be the EXACT same OTP
        $this->assertFalse($second['is_new']);
        $this->assertEquals($firstOtp, $second['otp']);
        $this->assertLessThanOrEqual(420, $second['remaining_seconds']);
        $this->assertGreaterThan(400, $second['remaining_seconds']);

        Carbon::setTestNow(); // Reset time
    }

    public function test_it_generates_new_otp_after_10_minutes_expiry(): void
    {
        $email = 'user@example.com';

        $first = $this->otpService->getOrCreateOtp($email, 'verification');
        $firstOtp = $first['otp'];

        // 11 minutes pass (OTP expired)
        Carbon::setTestNow(now()->addMinutes(11));

        $second = $this->otpService->getOrCreateOtp($email, 'verification');

        $this->assertTrue($second['is_new']);
        $this->assertEquals(600, $second['remaining_seconds']);

        Carbon::setTestNow();
    }

    public function test_it_verifies_valid_otp_and_consumes_it(): void
    {
        $email = 'verify@example.com';
        $data = $this->otpService->getOrCreateOtp($email, 'email_verify');

        // Invalid OTP check
        $this->assertFalse($this->otpService->verify($email, '000000', 'email_verify'));

        // Valid OTP check
        $this->assertTrue($this->otpService->verify($email, $data['otp'], 'email_verify'));

        // Once consumed, should no longer be valid
        $this->assertFalse($this->otpService->verify($email, $data['otp'], 'email_verify'));
    }

    public function test_resend_cooldown_works_properly(): void
    {
        $email = 'cooldown@example.com';

        $check = $this->otpService->checkResendCooldown($email, 'login');
        $this->assertTrue($check['can_resend']);

        // Set 60 sec cooldown
        $this->otpService->setResendCooldown($email, 'login', 60);

        $checkAfter = $this->otpService->checkResendCooldown($email, 'login');
        $this->assertFalse($checkAfter['can_resend']);
        $this->assertGreaterThan(0, $checkAfter['cooldown_remaining_seconds']);

        // Fast forward 61 seconds
        Carbon::setTestNow(now()->addSeconds(61));

        $checkExpired = $this->otpService->checkResendCooldown($email, 'login');
        $this->assertTrue($checkExpired['can_resend']);

        Carbon::setTestNow();
    }
}
