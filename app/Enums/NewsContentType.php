<?php

namespace App\Enums;

enum NewsContentType: string
{
    case NEWS = 'news';
    case PRESS_RELEASE = 'press_release';
    case ANNOUNCEMENT = 'announcement';
    case MARKET_UPDATE = 'market_update';
    case EXPERT_INSIGHT = 'expert_insight';
    case PUBLICATION = 'publication';
    case MEDIA_COVERAGE = 'media_coverage';
    case EVENT = 'event';
    case INTERVIEW = 'interview';
    case VIDEO = 'video';
    case OTHER = 'other';

    /**
     * Get all values as an array.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
