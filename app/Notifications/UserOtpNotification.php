<?php

namespace App\Notifications;

use App\Enums\EmailTemplateType;
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

class UserOtpNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
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
     * The recipient user name if known.
     */
    public ?string $userName;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $otp, string $purpose = 'registration', ?string $userName = null)
    {
        $this->otp = $otp;
        $this->purpose = $purpose;
        $this->userName = $userName;
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
            'registration', 'register' => 'Account Registration',
            'login' => 'Account Login',
            'password_reset' => 'Password Reset',
            default => 'Verification',
        };

        // Resolve recipient name if not already provided
        $userName = $this->userName;
        if (! $userName) {
            if ($notifiable instanceof User) {
                $userName = $notifiable->full_name ?? $notifiable->name ?? 'User';
            } elseif (is_object($notifiable) && isset($notifiable->routes['mail'])) {
                $email = strtolower((string) $notifiable->routes['mail']);
                $foundUser = User::where('email', $email)->first();
                $userName = $foundUser ? ($foundUser->full_name ?? $foundUser->name ?? 'User') : 'User';
            } else {
                $userName = 'User';
            }
        }

        // Check if an active EmailTemplate is configured
        $templateKey = ($this->purpose === 'login') ? 'USER_LOGIN_OTP' : 'USER_OTP';
        $template = EmailTemplate::query()
            ->where('key', $templateKey)
            ->where('is_active', true)
            ->first();

        if (! $template && $templateKey !== 'USER_OTP') {
            $template = EmailTemplate::query()
                ->where('key', 'USER_OTP')
                ->where('is_active', true)
                ->first();
        }

        if ($template) {
            /** @var EmailTemplateRenderer $renderer */
            $renderer = app(EmailTemplateRenderer::class);
            $replacements = [
                'UserName' => $userName,
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
            ->subject("{$purposeLabel} OTP - {$appName}")
            ->line("You have requested a verification OTP for {$purposeLabel}.")
            ->line("Your one-time verification code is: **{$this->otp}**")
            ->line('This OTP is valid for 10 minutes. If you request again within this period, the same code will remain active.')
            ->line('If you did not make this request, you can safely ignore this email.');
    }
}
