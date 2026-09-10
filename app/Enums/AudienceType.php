<?php

namespace App\Enums;

enum AudienceType: string
{
    case SINGLE_USER = 'single_user';
    case SELECTED_USERS = 'selected_users';
    case ALL_USERS = 'all_users';
    case TOPIC = 'topic';

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
