<?php

namespace App\Console\Commands;

use App\Services\Letter\InspectMissingDocumentFonts;
use Illuminate\Console\Command;

use function Laravel\Prompts\table;

class MissingDocumentFonts extends Command
{
    protected $signature = 'document-fonts:missing';

    protected $description = 'Periksa kekurangan paket font dan kebutuhan font seluruh versi templat';

    public function handle(InspectMissingDocumentFonts $inspector): int
    {
        $result = $inspector->inspect();
        $this->section('Kekurangan Paket', ['Font', 'Varian', 'Berkas', 'Masalah'], $result['package'], 'Tidak ada kekurangan paket font.');
        $this->section('Font Template Belum Tersedia', ['Templat', 'Versi', 'Font', 'Varian', 'Fallback'], $result['templates'], 'Tidak ditemukan font template yang kurang pada versi yang berhasil diperiksa.');
        $this->section('Pemeriksaan Tidak Lengkap', ['Asal', 'Masalah'], $result['incomplete'], 'Seluruh pemeriksaan selesai.');
        $summary = $result['summary'];
        $this->line(sprintf('Paket: %d berkas diperiksa, %d temuan.', $summary['package_checked'], count($result['package'])));
        $this->line(sprintf('Templat: %d versi diperiksa, %d dilewati tanpa DOCX, %d versi memiliki temuan.', $summary['versions_checked'], $summary['versions_skipped'], $summary['versions_with_findings']));
        $this->line(sprintf('Font template kurang: %d; pemeriksaan tidak lengkap: %d.', count($result['templates']), count($result['incomplete'])));

        return $result['exit_code'];
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    private function section(string $title, array $headers, array $rows, string $empty): void
    {
        $this->newLine();
        $this->info($title);
        if ($rows === []) {
            $this->line($empty);

            return;
        }
        table(headers: $headers, rows: array_map(fn(array $row): array => array_map(fn(string $cell): string => preg_replace('/[\x00-\x1F\x7F]/u', ' ', $cell) ?? '', $row), $rows));
    }
}
