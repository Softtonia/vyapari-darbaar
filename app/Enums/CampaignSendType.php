<?php

namespace App\Enums;

enum CampaignSendType: string
{
    case NOW = 'now';
    case SCHEDULE = 'schedule';
    case TRIGGER = 'trigger';

    public function label(): string
    {
        return match ($this) {
            self::NOW => 'Sent Now',
            self::SCHEDULE => 'Schedule',
            self::TRIGGER => 'Trigger',
        };
    }
}
