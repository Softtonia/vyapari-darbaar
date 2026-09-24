<?php

namespace App\Services;

use App\Enums\CampaignEvent;
use App\Enums\CampaignSendType;
use App\Models\Campaign;
use App\Notifications\CampaignEmailNotification;

class CampaignEmailService
{
    /**
     * Trigger an email campaign for a specific event.
     *
     * @param CampaignEvent|string $event The trigger event name
     * @param mixed $notifiable The user/admin to send the email to
     * @param array $placeholders Dynamic data to replace in the template
     */
    public function triggerEvent($event, $notifiable, array $placeholders = []): void
    {
        if ($event instanceof CampaignEvent) {
            $event = $event->value;
        }

        // Find active campaigns that are set to trigger on this event
        $campaigns = Campaign::with('emailTemplate')
            ->where('is_active', true)
            ->where('send_type', CampaignSendType::TRIGGER->value)
            ->where('event', $event)
            ->get();

        foreach ($campaigns as $campaign) {
            $template = $campaign->emailTemplate;
            if (!$template || !$template->is_active) {
                continue;
            }

            // Replace placeholders in subject and body
            $subject = $this->replacePlaceholders($template->subject, $placeholders);
            $body = $this->replacePlaceholders($template->body, $placeholders);

            // Send via Notification (queueable)
            $notifiable->notify(new CampaignEmailNotification($subject, $body));
        }
    }

    /**
     * Replace placeholders like {{name}} with actual values.
     */
    private function replacePlaceholders(string $content, array $placeholders): string
    {
        foreach ($placeholders as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value ?? '', $content);
        }
        return $content;
    }
}
