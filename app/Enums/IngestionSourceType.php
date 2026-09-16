<?php

namespace App\Enums;

enum IngestionSourceType: string
{
    case REFERENCE_DATA = 'reference_data';
    case BHAVCOPY = 'bhavcopy';
    case HISTORICAL_BACKFILL = 'historical_backfill';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::REFERENCE_DATA => 'Reference Data',
            self::BHAVCOPY => 'Bhavcopy (EOD)',
            self::HISTORICAL_BACKFILL => 'Historical Backfill',
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
