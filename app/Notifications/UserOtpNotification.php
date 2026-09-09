<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class UserOtpNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The OTP code.
     *
     * @var string
     */
    public string $otp;

    /**
     * The purpose of the OTP.
     *
     * @var string
     */
    public string $purpose;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $otp, string $purpose = 'registration')
    {
        $this->otp = $otp;
        $this->purpose = $purpose;
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
        $purposeLabel = match ($this->purpose) {
            'registration', 'register' => 'Account Registration',
            'login' => 'Account Login',
            'password_reset' => 'Password Reset',
            default => 'Verification',
        };

        $appName = config('app.name', 'Vyapari Darbaar');

        return (new MailMessage)
            ->subject("{$purposeLabel} OTP - {$appName}")
            ->line("You have requested a verification OTP for {$purposeLabel}.")
            ->line("Your one-time verification code is: **{$this->otp}**")
            ->line('This OTP is valid for 10 minutes. If you request again within this period, the same code will remain active.')
            ->line('If you did not make this request, you can safely ignore this email.');
    }
}
