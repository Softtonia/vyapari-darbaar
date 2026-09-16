<?php

namespace App\Enums;

enum EmailTemplateType: string
{
    case HTML = 'html';
    case PLAIN = 'plain';

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
