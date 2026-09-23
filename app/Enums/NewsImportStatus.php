<?php

namespace App\Enums;

enum NewsImportStatus: string
{
    /**
     * Run created, job queued but not yet started.
     */
    case PENDING = 'pending';

    /**
     * Job has started; feed is being fetched and processed.
     */
    case PROCESSING = 'processing';

    /**
     * Feed processed successfully and items_failed = 0.
     */
    case COMPLETED = 'completed';

    /**
     * Feed was valid and overall processing succeeded,
     * but one or more individual items failed.
     */
    case PARTIAL = 'partial';

    /**
     * Fatal run-level failure:
     * - HTTP feed fetch failure after retries
     * - Invalid XML / non-RSS response
     * - PIB NewsSource missing or inactive
     * - Configured NewsCategory missing or inactive
     * - Unrecoverable database failure
     */
    case FAILED = 'failed';

    /**
     * Return the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING    => 'Pending',
            self::PROCESSING => 'Processing',
            self::COMPLETED  => 'Completed',
            self::PARTIAL    => 'Completed with Errors (Partial)',
            self::FAILED     => 'Failed',
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
