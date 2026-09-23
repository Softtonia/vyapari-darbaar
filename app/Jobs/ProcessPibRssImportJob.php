<?php

namespace App\Jobs;

use App\Enums\NewsImportStatus;
use App\Models\NewsImportRun;
use App\Services\PibNewsImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessPibRssImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of job attempts.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Job execution timeout in seconds.
     *
     * @var int
     */
    public $timeout = 300;

    /**
     * Backoff delays in seconds between retries.
     * Escalating: 1 min → 5 min → 15 min
     *
     * @var list<int>
     */
    public $backoff = [60, 300, 900];

    /**
     * Create a new job instance.
     *
     * @param  int  $runId  The ID of the pre-created NewsImportRun record
     */
    public function __construct(
        public readonly int $runId,
    ) {
        // Route this job to the dedicated news-import queue
        $this->onQueue(config('news_imports.pib.queue', 'news-import'));
    }

    /**
     * Execute the job.
     *
     * Loads the NewsImportRun, acquires Redis lock, runs the import,
     * and updates the run to its terminal state.
     */
    public function handle(PibNewsImportService $service): void
    {
        $run = NewsImportRun::find($this->runId);

        if (! $run) {
            Log::error('[ProcessPibRssImportJob] NewsImportRun not found', ['run_id' => $this->runId]);

            return;
        }

        // Guard against re-processing a run that's no longer pending
        // (e.g. manually cancelled or a previously-failed retry)
        if (! in_array($run->status->value ?? $run->status, [
            NewsImportStatus::PENDING->value,
            NewsImportStatus::PROCESSING->value, // allow retry on a stuck processing run
        ], true)) {
            Log::info('[ProcessPibRssImportJob] Skipped: run is not pending', [
                'run_id' => $this->runId,
                'status' => $run->status,
            ]);

            return;
        }

        Log::info('[ProcessPibRssImportJob] Starting', ['run_id' => $this->runId]);

        $service->executeRun($run);
    }

    /**
     * Handle job failure after all retries are exhausted.
     * Ensures the run is marked FAILED even if an uncaught exception escapes.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('[ProcessPibRssImportJob] Job failed permanently', [
            'run_id' => $this->runId,
            'error'  => $exception->getMessage(),
        ]);

        $run = NewsImportRun::find($this->runId);

        if ($run && ! in_array($run->status->value ?? $run->status, [
            NewsImportStatus::COMPLETED->value,
            NewsImportStatus::PARTIAL->value,
            NewsImportStatus::FAILED->value,
        ], true)) {
            $run->update([
                'status'        => NewsImportStatus::FAILED->value,
                'finished_at'   => now(),
                'error_summary' => [substr($exception->getMessage(), 0, 1000)],
            ]);
        }
    }
}
