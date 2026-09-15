<?php

namespace App\Enums;

enum MarketType: string
{
    case APMC = 'apmc';
    case PRINCIPAL_YARD = 'principal_yard';
    case SUB_YARD = 'sub_yard';
    case PRIVATE_MARKET = 'private_market';

    /**
     * Get the human-readable label for the market type.
     */
    public function label(): string
    {
        return match ($this) {
            self::APMC => 'APMC Mandi',
            self::PRINCIPAL_YARD => 'Principal Market Yard',
            self::SUB_YARD => 'Sub Market Yard',
            self::PRIVATE_MARKET => 'Private Market Yard',
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
