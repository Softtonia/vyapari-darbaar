<?php

namespace App\Jobs;

use App\Models\NewsImportRun;
use App\Services\SebiNewsImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Executes a SEBI RSS import run asynchronously.
 */
class ProcessSebiRssImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Job timeout (seconds). Give it plenty of time for HTTP + DB writes.
     */
    public $timeout = 300;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 1;

    public function __construct(
        public int $runId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(SebiNewsImportService $service): void
    {
        $run = NewsImportRun::find($this->runId);

        if (! $run) {
            Log::error('[ProcessSebiRssImportJob] NewsImportRun not found', ['run_id' => $this->runId]);

            return;
        }

        $lockKey = config('news_imports.sebi.lock_key', 'news:lock:import:sebi');
        $lockTtl = config('news_imports.sebi.lock_ttl', 600);

        $lock = Cache::lock($lockKey, $lockTtl);

        if (! $lock->get()) {
            Log::info('[SebiNewsImportService] Skipped: lock held by another process', [
                'run_id'   => $this->runId,
                'lock_key' => $lockKey,
            ]);

            $run->update([
                'status'        => \App\Enums\NewsImportStatus::FAILED,
                'error_summary' => ['Skipped: Another SEBI import process is currently running.'],
                'finished_at'   => now(),
            ]);

            return;
        }

        try {
            $service->executeRun($run);
        } finally {
            $lock->release();
        }
    }
}
