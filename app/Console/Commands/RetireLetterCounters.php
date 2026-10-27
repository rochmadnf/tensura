<?php

namespace App\Console\Commands;

use App\Services\LetterNumber\RetireLetterNumberCounters;
use Illuminate\Console\Command;
use Throwable;

class RetireLetterCounters extends Command
{
    protected $signature = 'letters:retire-counters {--check : Periksa kesiapan tanpa mengubah skema}';

    protected $description = 'Retire initialized legacy letter-number counters without guessing periods';

    public function handle(RetireLetterNumberCounters $retirement): int
    {
        try {
            $pending = $retirement->pending();
            if ($pending !== []) {
                $this->error('Siapkan periode angka lama sebelum penghapusan counter:');
                foreach ($pending as $number) {
                    $this->line($number);
                }

                return self::FAILURE;
            }
            if ($this->option('check')) {
                $this->info('Seluruh klasifikasi siap; tidak ada perubahan skema.');

                return self::SUCCESS;
            }

            return $this->call('migrate', ['--path' => 'database/migrations/retirement', '--force' => true, '--no-interaction' => true]);
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
