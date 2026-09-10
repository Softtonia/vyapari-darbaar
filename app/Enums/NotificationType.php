<?php

namespace App\Enums;

enum NotificationType: string
{
    case PUSH = 'push';
    case IN_APP = 'in_app';
    case PUSH_AND_IN_APP = 'push_and_in_app';

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
