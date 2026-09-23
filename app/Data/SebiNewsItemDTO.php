<?php

namespace App\Data;

/**
 * Immutable DTO representing a single item parsed from the SEBI RSS feed.
 *
 * Example SEBI feed item:
 * <item>
 *   <title>Appeal No. 7064 of 2026 filed by Amit Gangrade</title>
 *   <description>Appeal No. 7064 of 2026 filed by Amit Gangrade</description>
 *   <link>https://www.sebi.gov.in/enforcement/orders/sep-2026/appeal-no-7064-of-2026-filed-by-amit-gangrade_104657.html</link>
 *   <pubDate>22 Sep, 2026 +0530</pubDate>
 * </item>
 *
 * Stable identifier is extracted from the link (e.g. 104657).
 */
final readonly class SebiNewsItemDTO
{
    public function __construct(
        /** Stable ID extracted from the link URL */
        public string $externalId,

        /** Full official article URL */
        public string $sourceUrl,

        /** Raw title text from RSS <title> */
        public string $title,

        /** Publication date parsed from RSS <pubDate> (null if absent) */
        public ?\DateTimeImmutable $publishedOn,

        /** Description/excerpt from RSS <description> (null if absent) */
        public ?string $description,
    ) {}
}
