<?php

namespace App\Services;

use App\Data\SebiNewsItemDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Encapsulates the HTTP request and XML parsing for the SEBI RSS feed.
 */
class SebiRssProvider
{
    /**
     * Fetch and parse the SEBI RSS feed.
     *
     * @return list<SebiNewsItemDTO>
     */
    public function fetchLatestItems(): array
    {
        $url = config('news_imports.sebi.url');
        $timeout = config('news_imports.sebi.timeout_seconds', 30);

        try {
            $response = Http::withHeaders([
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Connection'      => 'keep-alive',
            ])
                ->timeout($timeout)
                ->get($url);

            if (! $response->successful()) {
                Log::error('[SebiRssProvider] Failed to fetch feed', [
                    'url'    => $url,
                    'status' => $response->status(),
                    'body'   => Str::limit($response->body(), 500),
                ]);

                throw new \RuntimeException("Failed to fetch SEBI RSS feed: HTTP {$response->status()}");
            }

            $xmlString = $response->body();
            
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);
            
            if ($xml === false) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                Log::error('[SebiRssProvider] Invalid XML response', ['errors' => $errors]);
                throw new \RuntimeException('Invalid XML response from SEBI RSS feed.');
            }

            $dtos = [];
            foreach ($xml->channel->item as $item) {
                $link = (string) $item->link;
                
                // Extract unique ID from SEBI link (e.g. _104657.html)
                $externalId = null;
                if (preg_match('/_(\d+)\.html$/i', $link, $matches)) {
                    $externalId = $matches[1];
                } else {
                    // Fallback to hashing the link if no ID found
                    $externalId = md5($link);
                }

                $pubDate = null;
                if (!empty($item->pubDate)) {
                    try {
                        $pubDate = new \DateTimeImmutable((string) $item->pubDate);
                    } catch (\Exception $e) {
                        // ignore malformed date
                    }
                }

                $dtos[] = new SebiNewsItemDTO(
                    externalId: $externalId,
                    sourceUrl: $link,
                    title: trim((string) $item->title),
                    publishedOn: $pubDate,
                    description: !empty($item->description) ? trim((string) $item->description) : null,
                );
            }

            Log::info('[SebiRssProvider] Feed fetched', [
                'url'        => $url,
                'item_count' => count($dtos),
            ]);

            return $dtos;

        } catch (\Exception $e) {
            Log::error('[SebiRssProvider] Exception during fetch', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
