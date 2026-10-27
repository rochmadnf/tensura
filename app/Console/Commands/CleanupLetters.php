<?php

namespace App\Console\Commands;

use App\Services\Letter\CleanupLetterFiles;
use Illuminate\Console\Command;
use Throwable;

class CleanupLetters extends Command
{
    protected $signature = 'letters:cleanup {--dry-run : Periksa berkas tanpa menghapusnya}';

    protected $description = 'Clean expired and orphan letter files while retaining official history';

    public function handle(CleanupLetterFiles $cleanup): int
    {
        try {
            $result = $cleanup->run((bool) $this->option('dry-run'));
            $this->info(sprintf('Layak: %d; dihapus: %d; dipertahankan: %d; gagal: %d.', $result['eligible'], $result['deleted'], $result['kept'], $result['failed']));

            return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Cleanup Surat gagal. Periksa konfigurasi dan log.');

            return self::FAILURE;
        }
    }
}
