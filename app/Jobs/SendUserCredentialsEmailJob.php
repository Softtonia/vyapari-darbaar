<?php

namespace App\Jobs;

use App\Mail\UserCredentialsMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendUserCredentialsEmailJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public int $timeout = 60;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    /**
     * Create a new job instance.
     *
     * @param  int  $userId
     * @param  string  $recipientEmail
     * @param  string  $renderedSubject
     * @param  string  $renderedBody
     */
    public function __construct(
        public int $userId,
        public string $recipientEmail,
        public string $renderedSubject,
        public string $renderedBody
    ) {
        $this->onConnection('redis');
        $this->onQueue('emails');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Pre-send safety check: verify user still exists in database
        if (! User::query()->where('id', $this->userId)->exists()) {
            return;
        }

        Mail::to($this->recipientEmail)->send(
            new UserCredentialsMail($this->renderedSubject, $this->renderedBody)
        );
    }
}
