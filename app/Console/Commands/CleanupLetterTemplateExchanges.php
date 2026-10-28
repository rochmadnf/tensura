<?php

namespace App\Console\Commands;

use App\Services\LetterTemplate\LetterTemplateExchangeStage;
use Illuminate\Console\Command;

class CleanupLetterTemplateExchanges extends Command
{
    protected $signature = 'letter-templates:cleanup-exchanges';

    protected $description = 'Hapus paket ekspor dan pemeriksaan impor yang kedaluwarsa';

    public function handle(LetterTemplateExchangeStage $stages): int
    {
        $this->info('Paket kedaluwarsa dibersihkan: ' . $stages->cleanup());

        return self::SUCCESS;
    }
}
