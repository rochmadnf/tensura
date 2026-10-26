<?php

namespace App\Jobs;

use App\Services\Letter\RunLetterPreview;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateLetterPreview implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    public bool $failOnTimeout = true;

    public function __construct(public string $operationUuid)
    {
        $this->tries = config('letters.max_attempts');
        $this->timeout = config('letters.job_timeout');
        $this->onConnection('letters');
        $this->onQueue('letters');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function handle(RunLetterPreview $runner): void
    {
        $runner->run($this->operationUuid);
    }

    public function failed(?Throwable $exception): void
    {
        app(RunLetterPreview::class)->failed($this->operationUuid);
    }
}
