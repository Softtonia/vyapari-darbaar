<?php

namespace App\Enums;

enum DeviceType: string
{
    case ANDROID = 'android';
    case IOS = 'ios';
    case WEB = 'web';
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
