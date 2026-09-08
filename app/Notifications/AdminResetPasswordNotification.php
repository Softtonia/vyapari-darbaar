<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class AdminResetPasswordNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The password reset token.
     *
     * @var string
     */
    public string $token;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $token)
    {
        $this->token = $token;
        $this->onConnection('redis');
        $this->onQueue('emails');
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
        $frontendUrl = rtrim((string) config('app.frontend_admin_url', 'http://localhost:3000'), '/');
        $email = $notifiable->getEmailForPasswordReset();
        $resetUrl = $frontendUrl.'/admin/reset-password?token='.urlencode($this->token).'&email='.urlencode($email);

        return (new MailMessage)
            ->subject('Admin Password Reset Request')
            ->line('You are receiving this email because we received a password reset request for your administrator account.')
            ->action('Reset Password', $resetUrl)
            ->line('This password reset link will expire in 10 minutes.')
            ->line('If you did not request a password reset, no further action is required.');
    }
}
