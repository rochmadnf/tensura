<?php

namespace App\Console\Commands;

use App\Services\Letter\ChangeBundledDocumentFonts;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

class SyncDocumentFonts extends Command
{
    protected $signature = 'document-fonts:sync';

    protected $description = 'Verifikasi dan sinkronkan paket font dokumen ke penyimpanan privat';

    public function handle(ChangeBundledDocumentFonts $fonts): int
    {
        try {
            $result = $fonts->sync();
            foreach ($result['missing'] as $missing) {
                $this->warn('Varian belum tersedia dalam paket: ' . $missing);
            }
            foreach ($result['removed'] as $removed) {
                $this->line('Salinan paket dibersihkan: ' . $removed);
            }
            $this->info($result['count'] . ' font paket tersinkron.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception instanceof ValidationException ? collect($exception->errors())->flatten()->implode(' ') : $exception->getMessage());

            return self::FAILURE;
        }
    }
}
