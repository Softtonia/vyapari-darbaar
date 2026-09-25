<?php

namespace App\Notifications;

use App\Enums\EmailTemplateType;
use App\Models\Admin;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\HtmlString;

class AdminOtpNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The OTP code.
     */
    public string $otp;

    /**
     * The purpose of the OTP.
     */
    public string $purpose;

    /**
     * The recipient administrator name if known.
     */
    public ?string $adminName;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $otp, string $purpose = 'login', ?string $adminName = null)
    {
        $this->otp = $otp;
        $this->purpose = $purpose;
        $this->adminName = $adminName;
        $queueConnection = (string) config('queue.default', 'redis');
        $this->onConnection($queueConnection);
        if ($queueConnection === 'redis') {
            $this->onQueue('emails');
        }
        $this->tries = 3;
        $this->timeout = 60;
        $this->backoff = [10, 30, 60];
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $purposeLabel = match ($this->purpose) {
            'login', 'admin_login' => 'Administrator Login',
            'password_reset' => 'Administrator Password Reset',
            default => 'Administrator Verification',
        };

        // Resolve recipient administrator name
        $adminName = $this->adminName;
        if (! $adminName) {
            if ($notifiable instanceof Admin || $notifiable instanceof User) {
                $adminName = $notifiable->name ?? $notifiable->name ?? 'Administrator';
            } elseif (is_object($notifiable) && isset($notifiable->routes['mail'])) {
                $email = strtolower((string) $notifiable->routes['mail']);
                $foundAdmin = Admin::where('email', $email)->first();
                $adminName = $foundAdmin ? ($foundAdmin->name ?? $foundAdmin->name ?? 'Administrator') : 'Administrator';
            } else {
                $adminName = 'Administrator';
            }
        }

        // Check if active ADMIN_LOGIN_OTP or fallback template is configured
        $template = EmailTemplate::query()
            ->where('key', 'ADMIN_LOGIN_OTP')
            ->where('is_active', true)
            ->first();

        if (! $template) {
            $template = EmailTemplate::query()
                ->where('key', 'USER_LOGIN_OTP')
                ->where('is_active', true)
                ->first();
        }

        if ($template) {
            /** @var EmailTemplateRenderer $renderer */
            $renderer = app(EmailTemplateRenderer::class);
            $replacements = [
                'AdminName' => $adminName,
                'UserName' => $adminName,
                'Otp' => $this->otp,
                'Purpose' => $purposeLabel,
                'ExpiryMinutes' => '10',
            ];

            $renderedSubject = $renderer->render($template->subject, $replacements, $template->key);
            $renderedBody = $renderer->render($template->body, $replacements, $template->key);

            $mail = (new MailMessage)->subject($renderedSubject);

            $isHtml = ($template->type === EmailTemplateType::HTML)
                || ($template->type?->value === 'html')
                || ((string) $template->type === 'html');

            if ($isHtml) {
                $mail->view(['html' => new HtmlString($renderedBody)]);
            } else {
                $mail->line($renderedBody);
            }

            return $mail;
        }

        // Default clean fallback
        $appName = config('app.name', 'Vyapari Darbaar');

        return (new MailMessage)
            ->subject("Admin Security Code - {$appName}")
            ->line("You have requested a security OTP for {$purposeLabel}.")
            ->line("Your administrator verification code is: **{$this->otp}**")
            ->line('This code is valid for 10 minutes. If you request again within this period, the same code remains active.')
            ->line('If you did not initiate this administrator request, please change your credentials immediately.');
    }
}
