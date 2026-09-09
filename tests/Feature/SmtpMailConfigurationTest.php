<?php

namespace Tests\Feature;

use App\Jobs\SendUserCredentialsEmailJob;
use App\Mail\UserCredentialsMail;
use App\Models\Admin;
use App\Models\User;
use App\Notifications\AdminEmailUpdateOtpNotification;
use App\Notifications\AdminResetPasswordNotification;
use App\Notifications\UserEmailUpdateOtpNotification;
use App\Notifications\UserResetPasswordNotification;
use App\Services\EmailTemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SmtpMailConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    // ==========================================
    // 1. CONFIGURATION TESTS
    // ==========================================

    public function test_mail_configuration_has_expected_keys_and_defaults(): void
    {
        $mailConfig = config('mail');

        $this->assertIsArray($mailConfig);
        $this->assertArrayHasKey('default', $mailConfig);
        $this->assertArrayHasKey('mailers', $mailConfig);
        $this->assertArrayHasKey('from', $mailConfig);

        $smtpConfig = $mailConfig['mailers']['smtp'] ?? [];
        $this->assertEquals('smtp', $smtpConfig['transport']);
        $this->assertArrayHasKey('host', $smtpConfig);
        $this->assertArrayHasKey('port', $smtpConfig);
        $this->assertArrayHasKey('username', $smtpConfig);
        $this->assertArrayHasKey('password', $smtpConfig);
        $this->assertArrayHasKey('scheme', $smtpConfig);
        $this->assertArrayNotHasKey('encryption', $smtpConfig);

        $this->assertArrayHasKey('address', $mailConfig['from']);
        $this->assertArrayHasKey('name', $mailConfig['from']);
    }

    public function test_env_example_contains_safe_smtp_placeholders_and_no_real_secrets(): void
    {
        $envExample = file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString('MAIL_MAILER=smtp', $envExample);
        $this->assertStringContainsString('MAIL_SCHEME=smtp', $envExample);
        $this->assertStringContainsString('MAIL_HOST=smtp.example.com', $envExample);
        $this->assertStringContainsString('MAIL_PORT=587', $envExample);
        $this->assertStringContainsString('MAIL_FROM_ADDRESS=noreply@example.com', $envExample);
        $this->assertStringContainsString('MAIL_FROM_NAME="${APP_NAME}"', $envExample);
        $this->assertStringNotContainsString('MAIL_ENCRYPTION', $envExample);

        // Ensure no hardcoded raw passwords exist
        $this->assertStringNotContainsString('password123', $envExample);
        $this->assertStringNotContainsString('zoho', strtolower($envExample));
    }

    // ==========================================
    // 2. ARTISAN DIAGNOSTIC COMMAND TESTS
    // ==========================================

    public function test_mail_test_command_validates_recipient_email(): void
    {
        $this->artisan('mail:test', ['recipient' => 'invalid-email-string'])
            ->expectsOutputToContain("Invalid recipient email address: 'invalid-email-string'")
            ->assertExitCode(1);
    }

    public function test_mail_test_command_sends_diagnostic_email_safely(): void
    {
        Mail::fake();

        $this->artisan('mail:test', ['recipient' => 'diagnostics@example.com'])
            ->expectsOutputToContain('Vyapari Darbar — SMTP Diagnostic Test')
            ->expectsOutputToContain('diagnostics@example.com')
            ->assertExitCode(0);

        Mail::assertSent(\App\Mail\DiagnosticTestMail::class, function (\App\Mail\DiagnosticTestMail $mail) {
            return $mail->hasTo('diagnostics@example.com')
                && $mail->emailSubject === 'Vyapari Darbar — SMTP Diagnostic Test Email';
        });
    }

    public function test_mail_test_command_queues_email_when_queue_flag_is_passed(): void
    {
        Mail::fake();

        $this->artisan('mail:test', [
            'recipient' => 'queue-test@example.com',
            '--queue' => true,
        ])
            ->expectsOutputToContain("Diagnostic test email successfully queued on Redis queue 'emails'")
            ->assertExitCode(0);

        Mail::assertQueued(\App\Mail\DiagnosticTestMail::class, function (\App\Mail\DiagnosticTestMail $mail) {
            return $mail->hasTo('queue-test@example.com')
                && $mail->queue === 'emails'
                && $mail->connection === 'redis';
        });
    }

    // ==========================================
    // 3. HTML EMAIL TEMPLATE & RENDERING TESTS
    // ==========================================

    public function test_email_template_renderer_substitutes_all_placeholders(): void
    {
        $renderer = app(EmailTemplateRenderer::class);

        $template = 'Hello {{UserName}} ({{Username}}), your temporary password is {{TemporaryPassword}}. Welcome to {{CompanyName}}! Support: {{SupportEmail}}';

        $rendered = $renderer->render($template, [
            'UserName' => 'Ramesh Kumar',
            'Username' => 'ramesh.kumar',
            'TemporaryPassword' => 'Secr3t!2026',
        ], 'USER_ACCOUNT_CREATED');

        $this->assertStringContainsString('Ramesh Kumar', $rendered);
        $this->assertStringContainsString('ramesh.kumar', $rendered);
        $this->assertStringContainsString('Secr3t!2026', $rendered);
        $this->assertStringContainsString(config('app.name', 'Vyapari Darbaar'), $rendered);
        $this->assertStringNotContainsString('{{UserName}}', $rendered);
        $this->assertStringNotContainsString('{{TemporaryPassword}}', $rendered);
    }

    public function test_user_credentials_mail_renders_html_content_correctly(): void
    {
        $subject = 'Welcome to Vyapari Darbaar - Account Created';
        $bodyHtml = '<h1>Welcome Ramesh!</h1><p>Your account is ready.</p>';

        $mailable = new UserCredentialsMail($subject, $bodyHtml);

        $this->assertEquals($subject, $mailable->envelope()->subject);
        $this->assertEquals($bodyHtml, $mailable->content()->htmlString);
    }

    // ==========================================
    // 4. QUEUED JOB INTEGRATION TESTS
    // ==========================================

    public function test_send_user_credentials_job_is_configured_for_redis_emails_queue(): void
    {
        $user = User::create([
            'username' => 'testuser',
            'name' => 'Test User',
            'phone' => '9876543210',
            'email' => 'testuser@example.com',
            'password' => bcrypt('Password@123'),
            'status' => 'active',
        ]);

        $job = new SendUserCredentialsEmailJob(
            $user->id,
            'testuser@example.com',
            'Welcome',
            '<p>Your credentials</p>'
        );

        $this->assertEquals('redis', $job->connection);
        $this->assertEquals('emails', $job->queue);
        $this->assertEquals(3, $job->tries);
        $this->assertEquals(60, $job->timeout);
        $this->assertEquals([10, 30, 60], $job->backoff);
    }

    public function test_send_user_credentials_job_sends_email_when_handled(): void
    {
        Mail::fake();

        $user = User::create([
            'username' => 'testuser2',
            'name' => 'Test User 2',
            'phone' => '9876543211',
            'email' => 'testuser2@example.com',
            'password' => bcrypt('Password@123'),
            'status' => 'active',
        ]);

        $job = new SendUserCredentialsEmailJob(
            $user->id,
            'testuser2@example.com',
            'Account Activated',
            '<p>Login credentials inside.</p>'
        );

        $job->handle();

        Mail::assertSent(UserCredentialsMail::class, function (UserCredentialsMail $mail) {
            return $mail->hasTo('testuser2@example.com')
                && $mail->emailSubject === 'Account Activated'
                && str_contains($mail->emailBody, 'Login credentials inside.');
        });
    }

    // ==========================================
    // 5. TRANSACTIONAL NOTIFICATIONS TESTS
    // ==========================================

    public function test_admin_reset_password_notification_is_queued_on_emails_queue(): void
    {
        Notification::fake();

        $admin = Admin::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'password' => bcrypt('Password@123'),
            'status' => 'active',
        ]);

        $notification = new AdminResetPasswordNotification('sample-reset-token-123');

        $this->assertEquals('redis', $notification->connection);
        $this->assertEquals('emails', $notification->queue);
        $this->assertEquals(3, $notification->tries);
        $this->assertEquals(60, $notification->timeout);

        $admin->notify($notification);

        Notification::assertSentTo($admin, AdminResetPasswordNotification::class);
    }

    public function test_user_reset_password_notification_is_queued_on_emails_queue(): void
    {
        Notification::fake();

        $user = User::create([
            'username' => 'user_reset',
            'name' => 'User Reset',
            'phone' => '9876543212',
            'email' => 'userreset@example.com',
            'password' => bcrypt('Password@123'),
            'status' => 'active',
        ]);

        $notification = new UserResetPasswordNotification('sample-user-token-456');

        $this->assertEquals('redis', $notification->connection);
        $this->assertEquals('emails', $notification->queue);
        $this->assertEquals(3, $notification->tries);

        $user->notify($notification);

        Notification::assertSentTo($user, UserResetPasswordNotification::class);
    }

    public function test_admin_email_update_otp_notification_is_queued_on_emails_queue(): void
    {
        Notification::fake();

        $admin = Admin::create([
            'first_name' => 'AdminOTP',
            'last_name' => 'User',
            'email' => 'adminotp@example.com',
            'password' => bcrypt('Password@123'),
            'status' => 'active',
        ]);

        $notification = new AdminEmailUpdateOtpNotification('654321');

        $this->assertEquals('redis', $notification->connection);
        $this->assertEquals('emails', $notification->queue);

        $admin->notify($notification);

        Notification::assertSentTo($admin, AdminEmailUpdateOtpNotification::class);
    }

    public function test_user_email_update_otp_notification_is_queued_on_emails_queue(): void
    {
        Notification::fake();

        $user = User::create([
            'username' => 'user_otp',
            'name' => 'User OTP',
            'phone' => '9876543213',
            'email' => 'userotp@example.com',
            'password' => bcrypt('Password@123'),
            'status' => 'active',
        ]);

        $notification = new UserEmailUpdateOtpNotification('123456');

        $this->assertEquals('redis', $notification->connection);
        $this->assertEquals('emails', $notification->queue);

        $user->notify($notification);

        Notification::assertSentTo($user, UserEmailUpdateOtpNotification::class);
    }
}
