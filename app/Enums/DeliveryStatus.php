<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case SENT = 'sent';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';

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
