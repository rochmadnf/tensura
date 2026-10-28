<?php

namespace App\Console\Commands;

use App\Services\Letter\AddDocumentFontToPackage;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

use function Laravel\Prompts\confirm;

class AddDocumentFont extends Command
{
    protected $signature = 'document-fonts:add
        {font : Lokasi berkas font TTF/OTF statis}
        {--webfont : Izinkan penyajian font ke browser sesuai lisensinya}
        {--sync : Langsung sinkronkan paket ke aplikasi}
        {--no-sync : Simpan paket tanpa sinkronisasi}';

    protected $description = 'Tambahkan font ke paket dengan metadata dan checksum otomatis';

    public function handle(AddDocumentFontToPackage $adder): int
    {
        if ($this->option('sync') && $this->option('no-sync')) {
            $this->error('Pilih salah satu: --sync atau --no-sync.');

            return self::FAILURE;
        }
        if (!$this->input->isInteractive() && !$this->option('sync') && !$this->option('no-sync')) {
            $this->error('Mode tanpa interaksi memerlukan --sync atau --no-sync.');

            return self::FAILURE;
        }
        try {
            $entry = $adder->add($this->argument('font'), (bool) $this->option('webfont'));
            $this->info('Font tersedia dalam paket: ' . $entry['family'] . ' · ' . $entry['style']);
            $this->line('Berkas: ' . $entry['file']);
            $this->line('SHA-256: ' . $entry['sha256']);
            $this->line('Izin server: ' . ($entry['server'] ? 'Ya' : 'Tidak') . '; izin browser: ' . ($entry['webfont'] ? 'Ya' : 'Tidak'));
            $sync = $this->option('sync') || (!$this->option('no-sync') && confirm('Kapan paket font disinkronkan?', true, 'Langsung sinkronkan', 'Sinkronkan nanti'));
            if (!$sync) {
                $this->info('Paket tersimpan. Sinkron nanti dengan: php artisan document-fonts:sync --no-interaction');

                return self::SUCCESS;
            }
            if ($this->call('document-fonts:sync', ['--no-interaction' => true]) !== self::SUCCESS) {
                $this->error('Paket tetap tersimpan, tetapi sinkronisasi gagal. Coba lagi: php artisan document-fonts:sync --no-interaction');

                return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception instanceof ValidationException ? collect($exception->errors())->flatten()->implode(' ') : $exception->getMessage());

            return self::FAILURE;
        }
    }
}
