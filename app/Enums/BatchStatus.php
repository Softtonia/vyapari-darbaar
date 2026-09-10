<?php

namespace App\Enums;

enum BatchStatus: string
{
    case DRAFT = 'draft';
    case SCHEDULED = 'scheduled';
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case PARTIALLY_FAILED = 'partially_failed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

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
