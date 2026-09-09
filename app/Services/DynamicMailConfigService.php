<?php

namespace App\Services;

use App\Mail\DiagnosticTestMail;
use App\Models\SmtpSetting;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class DynamicMailConfigService
{
    public const CACHE_KEY = 'settings:smtp';
    public const CACHE_TTL = 3600;

    /**
     * Immutable snapshot of original configuration captured from config() on instantiation.
     *
     * @var array{default: mixed, smtp: mixed, from: mixed}
     */
    protected array $fallback;

    public function __construct()
    {
        $this->fallback = [
            'default' => config('mail.default', 'smtp'),
            'smtp' => config('mail.mailers.smtp', []),
            'from' => config('mail.from', []),
        ];
    }

    /**
     * Get the singleton SMTP setting (cached).
     */
    public function getSettings(): ?SmtpSetting
    {
        try {
            $setting = Cache::get(self::CACHE_KEY);

            if ($setting instanceof SmtpSetting) {
                return $setting;
            }

            if ($setting !== null) {
                Cache::forget(self::CACHE_KEY);
            }
        } catch (Throwable) {
            Cache::forget(self::CACHE_KEY);
        }

        $setting = SmtpSetting::query()->find(1);

        if ($setting) {
            Cache::put(self::CACHE_KEY, $setting, self::CACHE_TTL);
        }

        return $setting;
    }

    /**
     * Apply active dynamic SMTP configuration, or restore fallback if disabled / missing.
     */
    public function apply(): void
    {
        $smtp = $this->getSettings();

        if ($smtp && $smtp->isActive()) {
            $this->applyConfiguration($smtp);
        } else {
            $this->restoreFallback();
        }
    }

    /**
     * Apply saved database SMTP configuration regardless of status flag (for testing).
     */
    public function applySavedForTest(): SmtpSetting
    {
        $smtp = SmtpSetting::query()->find(1);

        if (! $smtp) {
            throw new \RuntimeException('SMTP configuration is not configured.');
        }

        $this->applyConfiguration($smtp);

        return $smtp;
    }

    /**
     * Restore baseline environment-backed configuration snapshot.
     */
    public function restoreFallback(): void
    {
        config([
            'mail.default' => $this->fallback['default'],
            'mail.mailers.smtp' => $this->fallback['smtp'],
            'mail.from' => $this->fallback['from'],
        ]);

        app(MailManager::class)->purge('smtp');
    }

    /**
     * Apply the given SmtpSetting model values to the runtime Laravel mail configuration.
     */
    protected function applyConfiguration(SmtpSetting $smtp): void
    {
        $scheme = match (strtolower((string) $smtp->encryption)) {
            'ssl', 'smtps' => 'smtps',
            default => 'smtp',
        };

        $mailer = $smtp->mailer ?: 'smtp';
        $fromEmail = $smtp->from_email ?? $smtp->from_address;
        $fromName = $smtp->from_name ?: config('app.name', 'Vyapari Darbar');

        config([
            'mail.default' => $mailer,

            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.scheme' => $scheme,
            'mail.mailers.smtp.host' => $smtp->host,
            'mail.mailers.smtp.port' => (int) $smtp->port,
            'mail.mailers.smtp.username' => $smtp->username,
            'mail.mailers.smtp.password' => $smtp->password,
            'mail.mailers.smtp.encryption' => in_array(strtolower((string) $smtp->encryption), ['tls', 'ssl', 'starttls'], true) ? $smtp->encryption : null,

            'mail.from.address' => $fromEmail,
            'mail.from.name' => $fromName,
        ]);

        app(MailManager::class)->purge('smtp');
    }

    /**
     * Create or update the singleton SMTP settings record.
     *
     * @param  array<string, mixed>  $data
     * @return SmtpSetting
     *
     * @throws ValidationException
     */
    public function updateSettings(array $data): SmtpSetting
    {
        // Normalize from_address to from_email if needed
        if (! isset($data['from_email']) && isset($data['from_address'])) {
            $data['from_email'] = $data['from_address'];
        }

        // Normalize status
        if (isset($data['status'])) {
            $raw = $data['status'];
            if ($raw === true || $raw === '1' || $raw === 1 || strtolower((string) $raw) === 'active') {
                $data['status'] = 'active';
            } elseif ($raw === false || $raw === '0' || $raw === 0 || strtolower((string) $raw) === 'pending' || strtolower((string) $raw) === 'inactive') {
                $data['status'] = 'pending';
            }
        }

        $setting = DB::transaction(function () use ($data) {
            /** @var SmtpSetting|null $existing */
            $existing = SmtpSetting::query()->whereKey(1)->lockForUpdate()->first();

            // Password update logic
            $hasNewPassword = array_key_exists('password', $data)
                && $data['password'] !== null
                && $data['password'] !== ''
                && $data['password'] !== '********';

            if (! $hasNewPassword) {
                if ($existing && ! empty($existing->password)) {
                    unset($data['password']);
                } else {
                    throw ValidationException::withMessages([
                        'password' => ['The password field is required when configuring SMTP settings.'],
                    ]);
                }
            }

            return SmtpSetting::updateOrCreate(
                ['id' => 1],
                $data
            );
        });

        // Invalidate cache
        Cache::forget(self::CACHE_KEY);

        // Apply new configuration
        $this->apply();

        return $setting;
    }

    /**
     * Send a diagnostic email using the saved DB SMTP configuration.
     *
     * @param  string  $recipient
     * @return void
     *
     * @throws Throwable
     */
    public function sendDiagnosticEmail(string $recipient): void
    {
        $smtp = $this->applySavedForTest();

        $subject = 'Vyapari Darbar — SMTP Diagnostic Test Email';
        $appName = $smtp->from_name ?: config('app.name', 'Vyapari Darbar');
        $fromAddress = $smtp->from_email ?? $smtp->from_address;
        $fromName = $smtp->from_name ?: $appName;
        $timestamp = now()->toDateTimeString();
        $encryption = $smtp->encryption ?: 'none';

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>SMTP Diagnostic Test</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #2c3e50; color: #ffffff; padding: 20px; text-align: center; border-radius: 6px 6px 0 0;">
        <h2 style="margin: 0;">{$appName} — Dynamic SMTP Delivery Test</h2>
    </div>
    <div style="border: 1px solid #e2e8f0; border-top: none; padding: 25px; border-radius: 0 0 6px 6px; background-color: #ffffff;">
        <p>Hello,</p>
        <p>This is a diagnostic test email verifying that the dynamic SMTP settings are operational.</p>
        <div style="background-color: #f8fafc; border-left: 4px solid #38a169; padding: 12px 16px; margin: 20px 0;">
            <strong>Test Details:</strong>
            <ul style="margin: 8px 0 0 0; padding-left: 20px;">
                <li><strong>Mailer:</strong> {$smtp->mailer}</li>
                <li><strong>Host:</strong> {$smtp->host}:{$smtp->port}</li>
                <li><strong>Encryption:</strong> {$encryption}</li>
                <li><strong>From:</strong> {$fromName} &lt;{$fromAddress}&gt;</li>
                <li><strong>Timestamp:</strong> {$timestamp} UTC</li>
            </ul>
        </div>
        <p style="font-size: 13px; color: #718096; margin-top: 30px;">This email was generated automatically by the Vyapari Darbar administrative SMTP test tool.</p>
    </div>
</body>
</html>
HTML;

        $mailable = new DiagnosticTestMail($htmlBody, $subject, $fromAddress, $fromName);

        try {
            Mail::to($recipient)->sendNow($mailable);
        } catch (Throwable $e) {
            Log::error('Dynamic SMTP diagnostic delivery failed', [
                'recipient' => $recipient,
                'host' => $smtp->host,
                'port' => $smtp->port,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            // Restore operational config state (respecting status flag)
            $this->apply();
        }
    }
}
