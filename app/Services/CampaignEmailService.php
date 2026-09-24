<?php

namespace App\Services;

use App\Enums\CampaignEvent;
use App\Enums\CampaignSendType;
use App\Models\Campaign;
use App\Notifications\CampaignEmailNotification;

class CampaignEmailService
{
    public function triggerEvent($event, $notifiable, array $placeholders = []): void
    {
        if ($event instanceof CampaignEvent) {
            $event = $event->value;
        }

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

            $subject = $this->replacePlaceholders($template->subject, $placeholders);
            $body = $this->replacePlaceholders($template->body, $placeholders);

            $notifiable->notify(new CampaignEmailNotification($subject, $body));
        }
    }

    private function replacePlaceholders(string $content, array $placeholders): string
    {
        foreach ($placeholders as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value ?? '', $content);
        }
        return $content;
    }
}
