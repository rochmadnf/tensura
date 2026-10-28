<?php

namespace App\Console\Commands;

use App\Models\DocumentFont;
use App\Services\Letter\DocumentFontPackage;
use App\Services\Letter\DocumentFontRegistry;
use App\Services\Letter\InstallDocumentFont;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class SyncDocumentFonts extends Command
{
    protected $signature = 'document-fonts:sync';

    protected $description = 'Verifikasi dan sinkronkan paket font dokumen ke penyimpanan privat';

    public function handle(DocumentFontPackage $package, DocumentFontRegistry $registry, InstallDocumentFont $installer): int
    {
        try {
            $manifest = $package->manifest();
            foreach ($manifest['missing'] as $missing) {
                $this->warn('Varian belum tersedia dalam paket: ' . $missing);
            }
            $entries = [];
            $keys = [];
            foreach ($manifest['fonts'] as $entry) {
                $path = config('document-fonts.package_directory') . '/' . $entry['file'];
                if (!$entry['server'] || !is_file($path) || hash_file('sha256', $path) !== $entry['sha256']) {
                    throw new RuntimeException('Izin server, berkas, atau checksum font tidak valid: ' . $entry['file']);
                }
                $face = $registry->scan($path);
                if ($face['family'] !== $entry['family'] || $face['style'] !== $entry['style']) {
                    throw new RuntimeException('Metadata font tidak sesuai manifest: ' . $entry['file']);
                }
                $key = $registry->faceKey($face['family'], $face['style']);
                if (isset($keys[$key]) || DocumentFont::query()->where('face_key', $key)->where('sha256', '!=', $entry['sha256'])->exists()) {
                    throw new RuntimeException('Konflik keluarga dan varian font: ' . $entry['family'] . ' · ' . $entry['style']);
                }
                $keys[$key] = true;
                $entries[] = [$path, $entry];
            }
            foreach ($entries as [$path, $entry]) {
                $installer->installFile($path, $entry['file'], bundled: true);
            }
            $registry->rebuild();
            $this->info(count($entries) . ' font paket tersinkron.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception instanceof ValidationException ? collect($exception->errors())->flatten()->implode(' ') : $exception->getMessage());

            return self::FAILURE;
        }
    }
}
