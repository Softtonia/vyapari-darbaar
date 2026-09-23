<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPibRssImportJob;
use App\Services\PibNewsImportService;
use Illuminate\Console\Command;

class ImportPibRssCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:import-pib-rss
                            {--sync : Run the import synchronously in the current process (for local/testing use)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import PIB (Press Information Bureau) press releases from the RSS feed into the News CMS as draft articles';

    /**
     * Execute the console command.
     *
     * Default mode: creates a PENDING run record and dispatches the job to the
     * news-import queue — the same lifecycle used by the Admin trigger API.
     *
     * --sync mode: creates the run and executes the import synchronously.
     * Uses the same service/run lifecycle — not a separate implementation.
     */
    public function handle(PibNewsImportService $service): int
    {
        if (! config('news_imports.pib.enabled', true)) {
            $this->warn('PIB RSS import is disabled (PIB_RSS_ENABLED=false). Exiting.');

            return self::SUCCESS;
        }

        // Create the pending run record first (mirrors Admin trigger flow)
        $run = $service->createPendingRun(triggeredBy: null);

        $this->info("Created import run #{$run->id} (status: pending).");

        if ($this->option('sync')) {
            $this->info('Running synchronously (--sync mode)...');

            try {
                $service->executeRun($run);
                $run->refresh();
                $this->info("Import completed — status: {$run->status->value}, imported: {$run->items_imported}, skipped: {$run->items_skipped}, failed: {$run->items_failed}");
            } catch (\Throwable $e) {
                $this->error('Import failed: ' . $e->getMessage());

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        // Dispatch to dedicated queue
        ProcessPibRssImportJob::dispatch($run->id);

        $this->info("Job dispatched to 'news-import' queue. Run ID: {$run->id}");
        $this->line('  Monitor progress: php artisan queue:work --queue=news-import');

        return self::SUCCESS;
    }
}
