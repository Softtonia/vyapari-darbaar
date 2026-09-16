<?php

namespace App\Enums;

enum OptionType: string
{
    case CALL = 'call';
    case PUT = 'put';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::CALL => 'Call Option (CE)',
            self::PUT => 'Put Option (PE)',
        };
    }

    /**
     * Get all raw values as an array.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
