<?php

namespace App\Services;

use App\Data\SebiNewsItemDTO;
use App\Enums\NewsImportStatus;
use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\NewsImportRun;
use App\Models\NewsSource;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates the SEBI RSS import run.
 */
class SebiNewsImportService
{
    public function __construct(
        protected SebiRssProvider $provider,
        protected NewsArticleService $articleService,
    ) {}

    /**
     * Create a new pending import run record.
     */
    public function createPendingRun(?int $triggeredBy = null): NewsImportRun
    {
        $source = $this->resolveSource();

        return NewsImportRun::create([
            'news_source_id'   => $source->id,
            'status'           => NewsImportStatus::PENDING,
            'feed_url'         => config('news_imports.sebi.url'),
            'triggered_by'     => $triggeredBy,
            'items_received'   => 0,
            'items_imported'   => 0,
            'items_skipped'    => 0,
            'items_failed'     => 0,
            'error_summary'    => null,
        ]);
    }

    /**
     * Execute the import run synchronously.
     */
    public function executeRun(NewsImportRun $run): void
    {
        $run->update([
            'status'     => NewsImportStatus::PROCESSING,
            'started_at' => now(),
        ]);

        try {
            $this->processRun($run);

            $run->update([
                'status'      => NewsImportStatus::COMPLETED,
                'finished_at' => now(),
            ]);

            Log::info('[SebiNewsImportService] Run finished', [
                'run_id'   => $run->id,
                'status'   => 'completed',
                'imported' => $run->items_imported,
                'skipped'  => $run->items_skipped,
                'failed'   => $run->items_failed,
            ]);

        } catch (\Throwable $e) {
            $errors = $run->error_summary ?? [];
            $errors[] = "Run failed: " . $e->getMessage();

            $run->update([
                'status'        => NewsImportStatus::FAILED,
                'finished_at'   => now(),
                'error_summary' => $errors,
            ]);

            Log::error('[SebiNewsImportService] Run-level failure', [
                'run_id' => $run->id,
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Internal processor loop.
     */
    protected function processRun(NewsImportRun $run): void
    {
        $source = $this->resolveSource();
        
        $categorySlug = config('news_imports.sebi.default_category_slug', 'business');

        $items = $this->provider->fetchLatestItems();
        
        $maxItems = config('news_imports.sebi.max_items', 50);
        $items = array_slice($items, 0, $maxItems);

        $run->update(['items_received' => count($items)]);

        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        $category = null;

        foreach ($items as $dto) {
            try {
                // Check if already imported
                $exists = NewsArticle::where('news_source_id', $source->id)
                    ->where('external_id', $dto->externalId)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                if (!$category) {
                    $category = NewsCategory::firstOrCreate(
                        ['slug' => $categorySlug],
                        ['name' => ucwords(str_replace('-', ' ', $categorySlug)), 'is_active' => true]
                    );
                    $run->update(['news_category_id' => $category->id]);
                }

                $this->importItem($dto, $source, $category);
                $imported++;

            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "Item [{$dto->externalId}]: " . $e->getMessage();
                Log::warning('[SebiNewsImportService] Item failed', [
                    'run_id'      => $run->id,
                    'external_id' => $dto->externalId,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        $run->update([
            'items_imported' => $imported,
            'items_skipped'  => $skipped,
            'items_failed'   => $failed,
            'error_summary'  => empty($errors) ? null : $errors,
        ]);
    }

    /**
     * Resolve the SEBI NewsSource.
     */
    protected function resolveSource(): NewsSource
    {
        $code = config('news_imports.sebi.source_code', 'SEBI');
        $source = NewsSource::where('code', $code)->first();

        if (! $source) {
            throw new \RuntimeException("NewsSource with code '{$code}' not found.");
        }

        return $source;
    }

    /**
     * Import a single SEBI RSS item as a DRAFT NewsArticle.
     */
    protected function importItem(
        SebiNewsItemDTO $dto,
        NewsSource $source,
        NewsCategory $category,
    ): NewsArticle {
        $defaultAuthor = config('news_imports.sebi.default_author', 'Securities and Exchange Board of India');

        $data = [
            'news_source_id'   => $source->id,
            'news_category_id' => $category->id,
            'content_type'     => \App\Enums\NewsContentType::PRESS_RELEASE->value,
            'title'            => \Illuminate\Support\Str::limit($dto->title, 250),
            'short_description' => $dto->description,
            'content'           => $dto->description ?? $dto->title,
            'author_name'       => $defaultAuthor,
            'source_url'        => $dto->sourceUrl,
            'published_on'      => $dto->publishedOn?->format('Y-m-d'),
            'published_at'      => null,
            'status'            => \App\Enums\NewsStatus::DRAFT->value,
            'is_featured'       => false,
            'is_breaking'       => false,
            'external_id'       => $dto->externalId,
            'imported_at'       => now(),
        ];

        $article = $this->articleService->createArticle($data, adminId: null);

        Log::debug('[SebiNewsImportService] Imported article', [
            'article_id'  => $article->id,
            'external_id' => $dto->externalId,
        ]);

        return $article;
    }
}
