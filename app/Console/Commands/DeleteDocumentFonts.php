<?php

namespace App\Console\Commands;

use App\Services\Letter\ChangeBundledDocumentFonts;
use App\Services\Letter\DocumentFontPackage;
use App\Services\Letter\InspectDocumentFontReferences;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multisearch;
use function Laravel\Prompts\table;

class DeleteDocumentFonts extends Command
{
    protected $signature = 'document-fonts:delete
        {fonts?* : Nama berkas font dalam manifest paket}
        {--yes : Lewati konfirmasi penghapusan}
        {--force : Izinkan hapus meskipun dipakai atau pemeriksaan tidak lengkap}';

    protected $description = 'Hapus beberapa varian font paket beserta salinan aktifnya';

    public function handle(DocumentFontPackage $package, ChangeBundledDocumentFonts $fonts, InspectDocumentFontReferences $references): int
    {
        $filenames = $this->argument('fonts');
        if (!$this->input->isInteractive() && ($filenames === [] || !$this->option('yes'))) {
            $this->error('Mode tanpa interaksi memerlukan nama berkas font dan --yes.');

            return self::FAILURE;
        }
        try {
            if ($filenames === []) {
                $entries = $package->manifest(refresh: true)['fonts'];
                if ($entries === []) {
                    $this->info('Tidak ada font dalam paket.');

                    return self::SUCCESS;
                }
                $filenames = multisearch(
                    label: 'Pilih font paket yang akan dihapus',
                    options: fn(string $query): array => collect($entries)->mapWithKeys(fn(array $entry): array => [$entry['file'] => $entry['family'] . ' · ' . $entry['style'] . ' — ' . $entry['file']])->filter(fn(string $label): bool => str_contains(mb_strtolower($label), mb_strtolower($query)))->all(),
                    placeholder: 'Cari keluarga, varian, atau nama berkas',
                    hint: 'Ketik untuk mencari, tekan Spasi untuk memilih, lalu Enter untuk melanjutkan.',
                );
                if ($filenames === []) {
                    $this->info('Tidak ada font dipilih. Tidak ada perubahan.');

                    return self::SUCCESS;
                }
            }
            $selected = $fonts->select($filenames);
            table(headers: ['Font', 'Varian', 'Berkas'], rows: $this->rows(array_map(fn(array $entry): array => [$entry['family'], $entry['style'], $entry['file']], $selected)));
            $usage = $references->inspect($selected);
            if ($usage['used'] !== []) {
                $this->warn('Font masih digunakan oleh template berikut:');
                table(headers: ['Font', 'Varian', 'Templat', 'Versi'], rows: $this->rows($usage['used']));
            }
            if ($usage['incomplete'] !== []) {
                $this->warn('Sebagian pemakaian font belum dapat dipastikan:');
                table(headers: ['Asal', 'Masalah'], rows: $this->rows($usage['incomplete']));
            }
            if (!$this->option('force') && ($usage['used'] !== [] || $usage['incomplete'] !== [])) {
                $this->error('Seluruh penghapusan diblokir. Gunakan --force setelah memeriksa dampaknya.');

                return self::FAILURE;
            }
            $this->line('Penghapusan berlaku pada paket dan salinan aktif. Font unggahan serta font sistem tetap tersedia.');
            if (!$this->option('yes') && !confirm('Hapus seluruh font yang dipilih?', default: false)) {
                $this->info('Penghapusan dibatalkan. Tidak ada perubahan.');

                return self::SUCCESS;
            }
            $count = $fonts->delete($selected, (bool) $this->option('force'));
            $this->info($count . ' varian font paket dihapus.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception instanceof ValidationException ? collect($exception->errors())->flatten()->implode(' ') : $exception->getMessage());

            return self::FAILURE;
        }
    }

    /** @param list<list<string>> $rows
     * @return list<list<string>>
     */
    private function rows(array $rows): array
    {
        return array_map(fn(array $row): array => array_map(fn(string $cell): string => preg_replace('/[\x00-\x1F\x7F]/u', ' ', $cell) ?? '', $row), $rows);
    }
}
