<?php

namespace App\Console\Commands;

use App\Services\LetterTemplate\CleanupLetterTemplateFiles as Cleanup;
use Illuminate\Console\Command;
use Throwable;

class CleanupLetterTemplateFiles extends Command
{
    protected $signature = 'letter-templates:cleanup {--dry-run : Tampilkan jumlah berkas yang layak dihapus tanpa menghapusnya}';

    protected $description = 'Membersihkan hasil uji DOCX kedaluwarsa/yatim dan berkas sementara tanpa menghapus sumber atau riwayat';

    public function handle(Cleanup $cleanup): int
    {
        try {
            $result = $cleanup->run((bool) $this->option('dry-run'));
            $this->info(sprintf('Layak dihapus: %d; dihapus: %d; dipertahankan: %d; gagal: %d.', $result['eligible'], $result['deleted'], $result['kept'], $result['failed']));

            return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Cleanup gagal. Periksa konfigurasi dan log aplikasi.');

            return self::FAILURE;
        }
    }
}
