<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TestMailDeliveryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:test
                            {recipient : The recipient email address}
                            {--queue : Queue the email on the Redis emails queue instead of sending synchronously}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a safe diagnostic test email to verify SMTP configuration and queue delivery.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $recipient = trim((string) $this->argument('recipient'));

        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid recipient email address: '{$recipient}'");

            return Command::FAILURE;
        }

        $defaultMailer = (string) config('mail.default', 'smtp');
        $smtpConfig = config("mail.mailers.{$defaultMailer}", config('mail.mailers.smtp', []));

        $fromAddress = (string) config('mail.from.address', 'noreply@vyaparidarbar.com');
        $fromName = (string) config('mail.from.name', config('app.name', 'Vyapari Darbar'));

        $host = $smtpConfig['host'] ?? '127.0.0.1';
        $port = (int) ($smtpConfig['port'] ?? 587);
        $scheme = $smtpConfig['scheme'] ?? ($port === 465 ? 'smtps' : 'smtp');
        $hasUsername = ! empty($smtpConfig['username']);
        $hasPassword = ! empty($smtpConfig['password']);

        $this->info('====================================================');
        $this->info('  Vyapari Darbar — SMTP Diagnostic Test');
        $this->info('====================================================');
        $this->table(
            ['Configuration Key', 'Effective Value'],
            [
                ['Default Mailer', $defaultMailer],
                ['SMTP Host', $host],
                ['SMTP Port', (string) $port],
                ['Effective Scheme', $scheme],
                ['From Address', $fromAddress],
                ['From Name', $fromName],
                ['Username Configured', $hasUsername ? 'Yes' : 'No (empty)'],
                ['Password Configured', $hasPassword ? 'Yes (hidden)' : 'No (empty)'],
                ['Target Recipient', $recipient],
                ['Send Mode', $this->option('queue') ? 'Queued (Redis: emails)' : 'Direct (Synchronous)'],
            ]
        );

        $subject = 'Vyapari Darbar — SMTP Diagnostic Test Email';
        $appName = config('app.name', 'Vyapari Darbar');
        $timestamp = now()->toDateTimeString();

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>SMTP Diagnostic Test</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #2c3e50; color: #ffffff; padding: 20px; text-align: center; border-radius: 6px 6px 0 0;">
        <h2 style="margin: 0;">{$appName} — Email Delivery Test</h2>
    </div>
    <div style="border: 1px solid #e2e8f0; border-top: none; padding: 25px; border-radius: 0 0 6px 6px; background-color: #ffffff;">
        <p>Hello,</p>
        <p>This is a diagnostic test email verifying that the SMTP delivery configuration is operational.</p>
        <div style="background-color: #f8fafc; border-left: 4px solid #38a169; padding: 12px 16px; margin: 20px 0;">
            <strong>Test Details:</strong>
            <ul style="margin: 8px 0 0 0; padding-left: 20px;">
                <li><strong>Mailer:</strong> {$defaultMailer}</li>
                <li><strong>Host:</strong> {$host}:{$port}</li>
                <li><strong>Timestamp:</strong> {$timestamp} UTC</li>
            </ul>
        </div>
        <p style="font-size: 13px; color: #718096; margin-top: 30px;">This email was generated automatically by the Vyapari Darbaar administrative CLI diagnostics.</p>
    </div>
</body>
</html>
HTML;

        $mailable = new \App\Mail\DiagnosticTestMail($htmlBody, $subject, $fromAddress, $fromName);

        if ($this->option('queue')) {
            $this->line('Dispatching test email to Redis queue (emails)...');

            try {
                Mail::to($recipient)->queue($mailable);

                $this->info("✓ Diagnostic test email successfully queued on Redis queue 'emails' for recipient: {$recipient}");

                return Command::SUCCESS;
            } catch (Throwable $e) {
                $this->error("Failed to queue test email: {$e->getMessage()}");
                Log::error('SMTP diagnostic queue test failed', ['error' => $e->getMessage()]);

                return Command::FAILURE;
            }
        }

        $this->line("Connecting to SMTP server ({$host}:{$port}) and sending test email...");
        $startTime = microtime(true);

        try {
            Mail::to($recipient)->sendNow($mailable);

            $duration = round(microtime(true) - $startTime, 2);
            $this->info("✓ Diagnostic test email sent successfully to {$recipient} in {$duration}s!");

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $duration = round(microtime(true) - $startTime, 2);
            $this->error("✗ SMTP Delivery Failed after {$duration}s: {$e->getMessage()}");

            Log::error('SMTP diagnostic delivery failed', [
                'recipient' => $recipient,
                'host' => $host,
                'port' => $port,
                'error' => $e->getMessage(),
            ]);

            return Command::FAILURE;
        }
    }
}
