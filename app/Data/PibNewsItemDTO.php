<?php

namespace App\Data;

/**
 * Immutable DTO representing a single item parsed from the PIB RSS feed.
 *
 * ACTUAL PIB RSS contract (inspected 2026-09-22):
 * - Feed fields present: <title>, <link> only
 * - NO <guid>, <description>, <pubDate>, <author>, <category>
 * - Stable identifier: PRID query parameter extracted from <link>
 *   e.g. https://pib.gov.in/PressReleaseIframePage.aspx?PRID=2313573
 *
 * All optional fields default to null — service layer applies defaults.
 */
final readonly class PibNewsItemDTO
{
    public function __construct(
        /** Stable PRID extracted from the link URL, e.g. "2313573" */
        public string $externalId,

        /** Full official PIB article URL */
        public string $sourceUrl,

        /** Raw title text from RSS <title> */
        public string $title,

        /** Publication date parsed from RSS <pubDate> (null if absent) */
        public ?\DateTimeImmutable $publishedOn,

        /** Author from RSS <author> or dc:creator (null if absent) */
        public ?string $author,

        /** Description/excerpt from RSS <description> (null if absent) */
        public ?string $description,
    ) {}
}
