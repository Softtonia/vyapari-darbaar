<?php

namespace App\Services;

use App\Data\PibNewsItemDTO;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Fetches and parses the PIB RSS feed into typed DTOs.
 *
 * ACTUAL PIB RSS contract (inspected 2026-09-22):
 *   RSS 2.0 feed — per item contains only:
 *     <title>   — press release headline (may be Unicode/Hindi)
 *     <link>    — official PIB page URL, contains PRID query parameter
 *
 *   Optional fields (NOT present in current PIB feed but handled defensively):
 *     <guid>, <description>, <pubDate>, <author>, <dc:creator>
 *
 * The PRID number extracted from the link URL is used as external_id.
 *
 * Security:
 *   - XML parsed with LIBXML_NONET to prevent SSRF via external entity
 *   - External entities are never loaded
 *   - LIBXML_NOENT is explicitly NOT used (untrusted remote content)
 *   - CDATA and HTML-entity fields are handled safely
 */
class PibRssProvider
{
    /**
     * Fetch the PIB RSS feed and parse it into PibNewsItemDTOs.
     *
     * @param  string  $url     Feed URL to fetch
     * @param  int     $timeout HTTP timeout in seconds
     * @param  int     $max     Maximum items to return
     * @return list<PibNewsItemDTO>
     *
     * @throws RuntimeException if the feed cannot be fetched or parsed
     */
    public function fetchItems(string $url, int $timeout = 30, int $max = 50): array
    {
        $xml = $this->fetchRawXml($url, $timeout);
        $items = $this->parseXml($xml, $max);

        Log::info('[PibRssProvider] Feed fetched', [
            'url'        => $url,
            'item_count' => count($items),
        ]);

        return $items;
    }

    /**
     * Fetch raw XML string from the remote feed URL.
     *
     * Uses Laravel HTTP client with:
     *   - connect + read timeout
     *   - retry on transient failures
     *   - SSL verification ON
     *   - descriptive User-Agent
     *
     * @throws RuntimeException on permanent fetch failure
     */
    protected function fetchRawXml(string $url, int $timeout): string
    {
        try {
            $response = Http::withHeaders([
                'User-Agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept'      => 'application/rss+xml, application/xml, text/xml;q=0.9, */*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
            ])
                ->timeout($timeout)
                ->connectTimeout(10)
                ->retry(3, 1000, function (\Exception $e) {
                    // Retry on connection errors and 5xx — not on 4xx
                    return $e instanceof ConnectionException
                        || (method_exists($e, 'response') && $e->response?->serverError());
                })
                ->throw()
                ->get($url);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Failed to fetch PIB RSS feed after retries: ' . $e->getMessage(),
                previous: $e
            );
        }

        $body = $response->body();

        if (empty(trim($body))) {
            throw new RuntimeException('PIB RSS feed returned an empty response body.');
        }

        return $body;
    }

    /**
     * Parse raw XML string into a list of PibNewsItemDTOs.
     *
     * Secure parsing:
     *   - LIBXML_NONET prevents network access during parse (SSRF guard)
     *   - External entities never loaded (no LIBXML_NOENT)
     *   - Each item parsed individually; a bad item does not abort the feed
     *
     * @return list<PibNewsItemDTO>
     *
     * @throws RuntimeException if the XML itself cannot be parsed
     */
    protected function parseXml(string $xml, int $max): array
    {
        // Disable libxml errors globally for this parse
        $previousUseInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $feed = simplexml_load_string(
            $xml,
            'SimpleXMLElement',
            LIBXML_NONET | LIBXML_COMPACT
        );

        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        if ($feed === false) {
            $errorMessages = array_map(
                fn (\LibXMLError $e) => trim($e->message),
                $errors
            );
            throw new RuntimeException(
                'PIB RSS feed XML is invalid: ' . implode('; ', $errorMessages)
            );
        }

        if (! isset($feed->channel->item)) {
            // Valid XML but no items — not an error
            return [];
        }

        $items = [];
        $count = 0;

        foreach ($feed->channel->item as $item) {
            if ($count >= $max) {
                break;
            }

            try {
                $dto = $this->parseItem($item);
                if ($dto !== null) {
                    $items[] = $dto;
                    $count++;
                }
            } catch (\Throwable $e) {
                // Log the bad item but continue processing the rest of the feed
                Log::warning('[PibRssProvider] Skipping malformed RSS item', [
                    'error' => $e->getMessage(),
                    'item'  => (string) ($item->title ?? ''),
                ]);
            }
        }

        return $items;
    }

    /**
     * Parse a single SimpleXML item element into a DTO.
     *
     * Returns null if the item is unusable (no title or no extractable ID).
     */
    protected function parseItem(\SimpleXMLElement $item): ?PibNewsItemDTO
    {
        $title = $this->extractText($item->title ?? null);
        $link  = $this->extractText($item->link ?? null);

        // Title and link are mandatory
        if (empty($title) || empty($link)) {
            return null;
        }

        // Extract stable PRID from URL query string
        // e.g. https://pib.gov.in/PressReleaseIframePage.aspx?PRID=2313573
        $externalId = $this->extractPrid($link);

        if (empty($externalId)) {
            // Fallback: use the full link as external_id (still deduplicated)
            $externalId = $link;
        }

        // Optional fields — present defensively in case PIB adds them
        $description = $this->extractText($item->description ?? null);
        $description = $description !== '' ? $this->sanitizeDescription($description) : null;

        $pubDate = null;
        $pubDateRaw = $this->extractText($item->pubDate ?? null);
        if (! empty($pubDateRaw)) {
            try {
                $pubDate = new \DateTimeImmutable($pubDateRaw);
            } catch (\Throwable) {
                $pubDate = null;
            }
        }

        // Check dc:creator namespace
        $author = null;
        $ns = $item->getNamespaces(true);
        if (isset($ns['dc'])) {
            $dc     = $item->children($ns['dc']);
            $author = $this->extractText($dc->creator ?? null);
        }
        if (empty($author)) {
            $author = $this->extractText($item->author ?? null);
        }
        $author = !empty($author) ? $author : null;

        return new PibNewsItemDTO(
            externalId:  $externalId,
            sourceUrl:   $link,
            title:       $title,
            publishedOn: $pubDate,
            author:      $author,
            description: $description,
        );
    }

    /**
     * Extract and clean text from a SimpleXML value (handles CDATA).
     */
    protected function extractText(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        // SimpleXMLElement with CDATA
        if ($value instanceof \SimpleXMLElement) {
            return trim((string) $value);
        }

        return trim((string) $value);
    }

    /**
     * Extract the PRID value from a PIB press release URL.
     * e.g. "https://pib.gov.in/...?PRID=2313573" → "2313573"
     */
    protected function extractPrid(string $url): string
    {
        $parsed = parse_url($url, PHP_URL_QUERY);
        if (! $parsed) {
            return '';
        }

        parse_str($parsed, $params);

        return (string) ($params['PRID'] ?? $params['prid'] ?? '');
    }

    /**
     * Safely strip HTML from description text.
     * We only want plain text — no HTML injection through RSS description.
     */
    protected function sanitizeDescription(string $raw): string
    {
        // Strip HTML tags, decode entities, normalise whitespace
        $plain = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = preg_replace('/\s+/', ' ', $plain);

        return trim((string) $plain);
    }
}
