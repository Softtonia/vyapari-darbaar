<?php

namespace App\Enums;

enum BatchUserStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case PARTIAL = 'partial';
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
