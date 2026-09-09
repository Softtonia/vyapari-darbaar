<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DatabaseCustomNotification extends Notification
{
    use Queueable;

    /**
     * Notification payload data.
     *
     * @var array{title: string, message: string, action_url?: string|null, type?: string|null, metadata?: array<string, mixed>}
     */
    public array $data;

    /**
     * Create a new notification instance.
     *
     * @param  array{title: string, message: string, action_url?: string|null, type?: string|null, metadata?: array<string, mixed>}  $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->data['title'],
            'message' => $this->data['message'],
            'action_url' => $this->data['action_url'] ?? null,
            'type' => $this->data['type'] ?? 'general',
            'metadata' => $this->data['metadata'] ?? [],
        ];
    }
}
