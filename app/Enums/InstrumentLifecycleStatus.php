<?php

namespace App\Enums;

enum InstrumentLifecycleStatus: string
{
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case DELISTED = 'delisted';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::EXPIRED => 'Expired',
            self::DELISTED => 'Delisted',
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
