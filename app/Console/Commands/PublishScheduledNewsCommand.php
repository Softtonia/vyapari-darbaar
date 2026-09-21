<?php

namespace App\Console\Commands;

use App\Enums\NewsStatus;
use App\Models\NewsArticle;
use App\Services\NewsArticleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublishScheduledNewsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:publish-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish due scheduled news articles with concurrency protection';

    public function handle(NewsArticleService $articleService): int
    {
        $lockKey = 'news:lock:publish-scheduled';
        $lock = Cache::lock($lockKey, 50);

        if (! $lock->get()) {
            $this->info('Another scheduled news publishing process is currently running. Skipping.');
            return self::SUCCESS;
        }

        try {
            $now = now();

            $dueArticles = NewsArticle::query()
                ->where('status', NewsStatus::SCHEDULED->value)
                ->whereNotNull('scheduled_at')
                ->where('scheduled_at', '<=', $now)
                ->with(['source', 'category'])
                ->orderBy('scheduled_at', 'asc')
                ->limit(200)
                ->get();

            if ($dueArticles->isEmpty()) {
                $this->info('No scheduled news articles are due for publication.');
                return self::SUCCESS;
            }

            $publishedCount = 0;
            $skippedCount = 0;

            foreach ($dueArticles as $article) {
                // Verify source and category are still active
                if (! $article->source || ! $article->source->status) {
                    Log::warning("Skipping scheduled publishing for article #{$article->id} ({$article->title}): News source is missing or inactive.");
                    $skippedCount++;
                    continue;
                }

                if (! $article->category || ! $article->category->status) {
                    Log::warning("Skipping scheduled publishing for article #{$article->id} ({$article->title}): News category is missing or inactive.");
                    $skippedCount++;
                    continue;
                }

                try {
                    $publishedAt = $article->published_at ?: ($article->scheduled_at ?: $now);
                    $publishedOn = $article->published_on ?: $publishedAt->toDateString();

                    $article->update([
                        'status' => NewsStatus::PUBLISHED->value,
                        'published_at' => $publishedAt,
                        'published_on' => $publishedOn,
                    ]);

                    $articleService->invalidateCaches($article);
                    $publishedCount++;

                    $this->info("Published article #{$article->id}: '{$article->title}'");
                } catch (Throwable $e) {
                    Log::error("Failed to publish scheduled article #{$article->id}: " . $e->getMessage());
                }
            }

            $this->info("Scheduled publishing completed. Published: {$publishedCount}, Skipped: {$skippedCount}.");
            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
