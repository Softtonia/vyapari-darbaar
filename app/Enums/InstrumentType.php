<?php

namespace App\Enums;

enum InstrumentType: string
{
    case FUTURE = 'future';
    case OPTION = 'option';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::FUTURE => 'Futures Contract',
            self::OPTION => 'Options Contract',
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
