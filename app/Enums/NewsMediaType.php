<?php

namespace App\Enums;

enum NewsMediaType: string
{
    case IMAGE = 'image';
    case PDF = 'pdf';
    case VIDEO = 'video';
    case DOCUMENT = 'document';
    case EXTERNAL_LINK = 'external_link';

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
