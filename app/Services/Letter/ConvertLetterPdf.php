<?php

namespace App\Services\Letter;

use App\Models\LetterArtifact;
use App\Models\LetterOperation;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ConvertLetterPdf
{
    public function directory(LetterOperation $operation, string $lease): string
    {
        return config('letters.paths.staging') . '/' . $operation->uuid . '/' . $lease;
    }

    /** @return array{path: string, sha256: string, size: int} */
    public function convert(LetterOperation $operation, LetterArtifact $word, string $lease, array $profile): array
    {
        $disk = Storage::disk(config('letters.disk'));
        $directory = $this->directory($operation, $lease);
        $disk->makeDirectory($directory . '/output');
        $source = Storage::disk($word->disk);
        if (!$disk->put($directory . '/document.docx', $source->get($word->path)) || hash_file('sha256', $disk->path($directory . '/document.docx')) !== $word->sha256) {
            throw ValidationException::withMessages(['pdf' => 'Word sumber berubah sebelum konversi.']);
        }
        $profileUrl = 'file://' . str_replace('%2F', '/', rawurlencode($disk->path($directory . '/profile')));
        $filter = 'pdf:writer_pdf_Export' . ($word->letter_version_id === null ? ':' . json_encode(['TiledWatermark' => ['type' => 'string', 'value' => 'DRAFT']], JSON_THROW_ON_ERROR) : '');
        $result = Process::path($disk->path($directory))->env([...$profile['environment'], 'SAL_USE_VCLPLUGIN' => 'svp'])->timeout(config('letters.pdf.timeout'))->run([config('document-fonts.executables.version'), '-env:UserInstallation=' . $profileUrl, '--headless', '--nologo', '--nodefault', '--nofirststartwizard', '--convert-to', $filter, '--outdir', $disk->path($directory . '/output'), $disk->path($directory . '/document.docx')]);
        if ($result->failed()) {
            throw new RuntimeException('LibreOffice conversion failed.');
        }
        $path = $directory . '/output/document.pdf';
        if (!$disk->exists($path)) {
            throw new RuntimeException('LibreOffice returned no PDF.');
        }
        $size = $disk->size($path);
        if ($size < 20 || $size > config('letters.pdf.max_bytes')) {
            throw ValidationException::withMessages(['pdf' => 'Ukuran PDF tidak valid atau melewati batas.']);
        }
        $handle = fopen($disk->path($path), 'rb');
        try {
            $header = fread($handle, 8);
            fseek($handle, -min(1024, $size), SEEK_END);
            $end = stream_get_contents($handle);
        } finally {
            fclose($handle);
        }
        if (!str_starts_with($header, '%PDF-') || !str_contains($end, '%%EOF')) {
            throw new RuntimeException('Converter output is not a complete PDF.');
        }

        return ['path' => $path, 'sha256' => hash_file('sha256', $disk->path($path)), 'size' => $size];
    }
}
