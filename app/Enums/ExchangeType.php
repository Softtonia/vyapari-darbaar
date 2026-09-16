<?php

namespace App\Enums;

enum ExchangeType: string
{
    case COMMODITY_DERIVATIVES = 'commodity_derivatives';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::COMMODITY_DERIVATIVES => 'Commodity Derivatives',
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
