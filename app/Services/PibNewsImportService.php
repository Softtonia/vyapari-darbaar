<?php

namespace App\Services;

use App\Data\PibNewsItemDTO;
use App\Enums\NewsContentType;
use App\Enums\NewsImportStatus;
use App\Enums\NewsStatus;
use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\NewsImportRun;
use App\Models\NewsSource;
use DomainException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Orchestrates the end-to-end PIB RSS import lifecycle.
 *
 * Responsibilities:
 *   1. Resolve PIB NewsSource (by config code) — fail fast if missing/inactive
 *   2. Resolve configured NewsCategory (by config slug) — fail fast if missing/inactive
 *   3. Fetch feed via PibRssProvider
 *   4. Bulk-check duplicates (external_id + source_url) — no per-item queries
 *   5. Import new items through existing NewsArticleService as DRAFT
 *   6. Update NewsImportRun counters and final status
 *
 * Separation:
 *   - XML fetch/parse       → PibRssProvider
 *   - Article create/update → NewsArticleService
 *   - Category resolution   → this service (simple slug lookup)
 *
 * All imported articles are status=draft, published_at=null, is_featured=false,
 * is_breaking=false — Admin must publish manually through the existing CMS flow.
 */
class PibNewsImportService
{
    /**
     * Maximum number of error messages stored in error_summary.
     * Prevents unbounded JSON growth.
     */
    private const MAX_ERROR_MESSAGES = 50;

    public function __construct(
        protected PibRssProvider $provider,
        protected NewsArticleService $articleService,
    ) {}

    /**
     * Create a new PENDING import run record.
     * Called by the controller BEFORE dispatching the job.
     */
    public function createPendingRun(?int $triggeredBy = null): NewsImportRun
    {
        return NewsImportRun::create([
            'news_source_id'   => null,
            'news_category_id' => null,
            'feed_url'         => config('news_imports.pib.url'),
            'status'           => NewsImportStatus::PENDING->value,
            'items_received'   => 0,
            'items_imported'   => 0,
            'items_skipped'    => 0,
            'items_failed'     => 0,
            'triggered_by'     => $triggeredBy,
        ]);
    }

    /**
     * Execute the import for an existing PENDING NewsImportRun.
     *
     * Acquires a Redis lock to prevent concurrent runs.
     * Updates the run record to PROCESSING, then COMPLETED / PARTIAL / FAILED.
     *
     * @throws LockTimeoutException if another import is already running (handled by job)
     */
    public function executeRun(NewsImportRun $run): void
    {
        $lockKey = config('news_imports.pib.lock_key', 'news:lock:import:pib');
        $lockTtl = (int) config('news_imports.pib.lock_ttl', 600);

        $lock = Cache::lock($lockKey, $lockTtl);

        if (! $lock->get()) {
            // Another import is in progress — mark this run accordingly
            $this->markRunFailed($run, 'Another PIB import is already running. This run was skipped.');

            Log::info('[PibNewsImportService] Skipped: lock held by another process', [
                'run_id'   => $run->id,
                'lock_key' => $lockKey,
            ]);

            return;
        }

        try {
            $this->processRun($run);
        } finally {
            $lock->release();
        }
    }

    /**
     * Inner import logic — called inside the Redis lock.
     */
    protected function processRun(NewsImportRun $run): void
    {
        // Mark as processing
        $run->update([
            'status'     => NewsImportStatus::PROCESSING->value,
            'started_at' => now(),
        ]);

        $errors = [];

        try {
            // 1. Resolve source
            $source = $this->resolveSource();

            // 2. Resolve categories
            $traderCategory = $this->resolveCategory('trader');
            $agricultureCategory = $this->resolveCategory('agriculture');

            // Update run with resolved IDs
            $run->update([
                'news_source_id'   => $source->id,
            ]);

            // 3. Fetch feed items
            $feedUrl = config('news_imports.pib.url');
            $timeout = (int) config('news_imports.pib.timeout_seconds', 30);
            $maxItems = (int) config('news_imports.pib.max_items', 50);

            $items = $this->provider->fetchItems($feedUrl, $timeout, $maxItems);

            $run->update(['items_received' => count($items)]);

            if (empty($items)) {
                $this->finishRun($run, 0, 0, 0, $errors);

                return;
            }

            // 4. Bulk duplicate check — no per-item queries
            $externalIds = array_filter(array_map(
                fn (PibNewsItemDTO $dto) => $dto->externalId,
                $items
            ));
            $sourceUrls = array_map(
                fn (PibNewsItemDTO $dto) => $dto->sourceUrl,
                $items
            );

            $existingByExternalId = $this->getExistingExternalIds($source->id, $externalIds);
            $existingBySourceUrl  = $this->getExistingSourceUrls($sourceUrls);

            // 5. Process items
            $imported = 0;
            $skipped  = 0;
            $failed   = 0;

            foreach ($items as $dto) {
                try {
                    // Primary duplicate check: (news_source_id, external_id)
                    if (isset($existingByExternalId[$dto->externalId])) {
                        $skipped++;
                        continue;
                    }

                    // Secondary duplicate check: source_url
                    if (isset($existingBySourceUrl[$dto->sourceUrl])) {
                        $skipped++;
                        continue;
                    }

                    // 5a. Classify item
                    $classification = $this->classifyItem($dto);
                    if ($classification === 'SKIP') {
                        $skipped++;
                        Log::debug('[PibNewsImportService] Skipped irrelevant item', ['external_id' => $dto->externalId]);
                        continue;
                    }

                    $category = $classification === 'AGRICULTURE' ? $agricultureCategory : $traderCategory;

                    // Import as DRAFT article
                    $article = $this->importItem($dto, $source, $category);

                    // Register in our in-memory sets so same-feed duplicates also skip
                    $existingByExternalId[$dto->externalId] = true;
                    $existingBySourceUrl[$dto->sourceUrl]    = true;

                    $imported++;

                    Log::debug('[PibNewsImportService] Imported article', [
                        'run_id'      => $run->id,
                        'article_id'  => $article->id,
                        'external_id' => $dto->externalId,
                    ]);
                } catch (Throwable $e) {
                    $failed++;
                    $message = "Item [{$dto->externalId}]: " . $e->getMessage();
                    $errors[] = $message;

                    Log::warning('[PibNewsImportService] Item import failed', [
                        'run_id'      => $run->id,
                        'external_id' => $dto->externalId,
                        'error'       => $e->getMessage(),
                    ]);
                }
            }

            $this->finishRun($run, $imported, $skipped, $failed, $errors);

        } catch (Throwable $e) {
            $this->markRunFailed($run, $e->getMessage());

            Log::error('[PibNewsImportService] Run-level failure', [
                'run_id' => $run->id,
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Resolve the existing PIB NewsSource by configured code.
     *
     * @throws DomainException if source is missing or inactive
     */
    protected function resolveSource(): NewsSource
    {
        $code = config('news_imports.pib.source_code', 'PIB');

        $source = NewsSource::where('code', $code)->first();

        if (! $source) {
            throw new DomainException(
                "PIB NewsSource with code '{$code}' not found. "
                . 'Please create and activate it in the Admin News Sources panel before running the importer.'
            );
        }

        if (! $source->status) {
            throw new DomainException(
                "PIB NewsSource '{$source->name}' (code: {$code}) is inactive. "
                . 'Activate it in the Admin News Sources panel before running the importer.'
            );
        }

        return $source;
    }

    /**
     * Resolve the existing NewsCategory by configured type ('trader' or 'agriculture').
     *
     * @throws DomainException if slug is configured but category is missing or inactive
     */
    protected function resolveCategory(string $type): NewsCategory
    {
        $slug = config("news_imports.pib.categories.{$type}.category_slug");

        if (empty($slug)) {
            throw new DomainException(
                "Configured PIB NewsCategory slug for '{$type}' is missing. "
                . 'Update PIB_RSS_'.strtoupper($type).'_CATEGORY_SLUG in .env.'
            );
        }

        $category = NewsCategory::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => ucfirst($type) . ' News',
                'description' => "Auto-created category for {$type} news imports",
                'status' => true,
            ]
        );

        if (! $category->status) {
            throw new DomainException(
                "Configured PIB {$type} NewsCategory '{$category->name}' (slug: {$slug}) is inactive. "
                . 'Activate it or update PIB_RSS_'.strtoupper($type).'_CATEGORY_SLUG in .env.'
            );
        }

        return $category;
    }

    /**
     * Classifies the given DTO into TRADER, AGRICULTURE, or SKIP.
     */
    protected function classifyItem(PibNewsItemDTO $dto): string
    {
        $text = strtolower($dto->title . ' ' . $dto->description . ' ' . $dto->content);

        $traderKeywords = config('news_imports.pib.categories.trader.keywords', []);
        $agKeywords = config('news_imports.pib.categories.agriculture.keywords', []);

        $traderCount = 0;
        foreach ($traderKeywords as $kw) {
            $traderCount += substr_count($text, strtolower($kw));
        }

        $agCount = 0;
        foreach ($agKeywords as $kw) {
            $agCount += substr_count($text, strtolower($kw));
        }

        if ($agCount === 0 && $traderCount === 0) {
            return 'SKIP';
        }

        if ($agCount >= $traderCount) {
            return 'AGRICULTURE';
        }

        return 'TRADER';
    }

    /**
     * Import a single PIB RSS item as a DRAFT NewsArticle.
     *
     * - status = draft
     * - published_at = null (Admin must publish manually)
     * - is_featured = false
     * - is_breaking = false
     * - external_id saved for idempotent re-import guard
     * - imported_at = now()
     *
     * Routes through existing NewsArticleService to preserve CMS domain logic.
     */
    protected function importItem(
        PibNewsItemDTO $dto,
        NewsSource $source,
        NewsCategory $category,
    ): NewsArticle {
        $defaultAuthor = config('news_imports.pib.default_author', 'Press Information Bureau');

        $data = [
            'news_source_id'   => $source->id,
            'news_category_id' => $category->id,
            'content_type'     => NewsContentType::PRESS_RELEASE->value,
            'title'            => $dto->title,
            // slug: let the existing NewsArticleService generate it from title
            'short_description' => $dto->description,
            'content'           => null, // Phase 1: no content — Admin adds via CMS
            'author_name'       => $dto->author ?? $defaultAuthor,
            'source_url'        => $dto->sourceUrl,
            'published_on'      => $dto->publishedOn?->format('Y-m-d'),
            // IMPORTANT: published_at intentionally NOT set
            // Admin publishes through the normal CMS publish flow
            'published_at'      => null,
            'status'            => NewsStatus::DRAFT->value,
            'is_featured'       => false,
            'is_breaking'       => false,
            // Import-tracking fields
            'external_id'       => $dto->externalId,
            'imported_at'       => now(),
        ];

        // Delegate to existing NewsArticleService — preserves slug uniqueness logic,
        // HTML sanitization hooks, and cache invalidation.
        // admin_id = null → created_by / updated_by will be null (scheduled import)
        return $this->articleService->createArticle($data, adminId: null);
    }

    /**
     * Bulk-fetch external IDs already present for this source.
     * Returns an associative map: [ external_id => true ]
     *
     * @param  list<string>  $externalIds
     * @return array<string, true>
     */
    protected function getExistingExternalIds(int $sourceId, array $externalIds): array
    {
        if (empty($externalIds)) {
            return [];
        }

        return NewsArticle::withTrashed()
            ->where('news_source_id', $sourceId)
            ->whereIn('external_id', $externalIds)
            ->pluck('external_id')
            ->mapWithKeys(fn (string $id) => [$id => true])
            ->all();
    }

    /**
     * Bulk-fetch source URLs already present across all articles.
     * Returns an associative map: [ source_url => true ]
     *
     * @param  list<string>  $urls
     * @return array<string, true>
     */
    protected function getExistingSourceUrls(array $urls): array
    {
        if (empty($urls)) {
            return [];
        }

        return NewsArticle::withTrashed()
            ->whereIn('source_url', $urls)
            ->pluck('source_url')
            ->mapWithKeys(fn (string $url) => [$url => true])
            ->all();
    }

    /**
     * Finalize the import run with outcome counters and correct terminal status.
     *
     * @param  list<string>  $errors
     */
    protected function finishRun(
        NewsImportRun $run,
        int $imported,
        int $skipped,
        int $failed,
        array $errors,
    ): void {
        // Determine terminal status
        if ($failed > 0 && $imported === 0 && $skipped === 0) {
            $status = NewsImportStatus::FAILED;
        } elseif ($failed > 0) {
            $status = NewsImportStatus::PARTIAL;
        } else {
            $status = NewsImportStatus::COMPLETED;
        }

        $run->update([
            'status'         => $status->value,
            'items_imported' => $imported,
            'items_skipped'  => $skipped,
            'items_failed'   => $failed,
            'finished_at'    => now(),
            'error_summary'  => empty($errors)
                ? null
                : array_slice($errors, 0, self::MAX_ERROR_MESSAGES),
        ]);

        Log::info('[PibNewsImportService] Run finished', [
            'run_id'   => $run->id,
            'status'   => $status->value,
            'imported' => $imported,
            'skipped'  => $skipped,
            'failed'   => $failed,
        ]);
    }

    /**
     * Mark a run as FAILED with a bounded error message.
     */
    protected function markRunFailed(NewsImportRun $run, string $message): void
    {
        $run->update([
            'status'        => NewsImportStatus::FAILED->value,
            'finished_at'   => now(),
            'error_summary' => [substr($message, 0, 1000)],
        ]);
    }
}
