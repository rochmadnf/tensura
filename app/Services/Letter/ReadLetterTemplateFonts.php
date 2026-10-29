<?php

namespace App\Services\Letter;

use App\Models\MasterData\LetterTemplateVersion;
use App\Services\LetterTemplate\InspectLetterTemplateDocx;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ReadLetterTemplateFonts
{
    public function __construct(private InspectLetterTemplateDocx $documents, private InspectDocumentFonts $fonts) {}

    /** @return array{requirements: list<array{family: string, bold: bool, italic: bool}>, limitations: list<string>} */
    public function read(LetterTemplateVersion $version): array
    {
        if ($version->source_disk === null || $version->source_disk === '') {
            throw new RuntimeException('Disk DOCX sumber belum tersedia.');
        }
        $disk = Storage::disk($version->source_disk);
        if ($version->source_path === null || !$disk->exists($version->source_path)) {
            throw new RuntimeException('DOCX sumber tidak ditemukan.');
        }
        if ($disk->size($version->source_path) > config('letter-templates.max_upload_kb') * 1024) {
            throw new RuntimeException('Ukuran DOCX sumber melewati batas konfigurasi aplikasi.');
        }
        if ($version->source_sha256 === null || hash_file('sha256', $disk->path($version->source_path)) !== $version->source_sha256) {
            throw new RuntimeException('Checksum DOCX sumber tidak sesuai atau belum tersedia.');
        }

        return $this->fonts->inspect($this->documents->read($disk->path($version->source_path)));
    }
}
