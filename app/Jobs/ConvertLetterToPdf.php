<?php

namespace App\Jobs;

use App\Services\Letter\RunLetterPdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ConvertLetterToPdf implements ShouldQueue
{
    use Queueable;

    public int $tries;

    public int $timeout;

    public bool $failOnTimeout = true;

    public function __construct(public string $operationUuid)
    {
        $this->tries = config('letters.max_attempts');
        $this->timeout = config('letters.job_timeout');
        $this->onConnection('letters');
        $this->onQueue('letters');
    }

    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function handle(RunLetterPdf $runner): void
    {
        $runner->run($this->operationUuid);
    }

    public function failed(?Throwable $exception): void
    {
        app(RunLetterPdf::class)->failed($this->operationUuid);
    }
}
