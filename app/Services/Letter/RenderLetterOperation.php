<?php

namespace App\Services\Letter;

use App\Models\LetterOperation;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RenderLetterOperation
{
    public function directory(LetterOperation $operation, string $lease): string
    {
        return config('letters.paths.staging') . '/' . $operation->uuid . '/' . $lease;
    }

    /** @return array{path: string, sha256: string, size: int, usage: array} */
    public function render(LetterOperation $operation, string $lease, ?array $snapshot = null, bool $draft = true, ?float $timeout = null): array
    {
        $disk = Storage::disk(config('letters.disk'));
        $directory = $this->directory($operation, $lease);
        $disk->makeDirectory($directory);
        $source = Storage::disk($operation->payload['source_disk']);
        if (!$source->exists($operation->payload['source_path']) || hash_file('sha256', $source->path($operation->payload['source_path'])) !== $operation->payload['source_sha256']) {
            throw ValidationException::withMessages(['preview' => 'DOCX sumber hilang atau berubah.']);
        }
        if (!$disk->put($directory . '/input.json', json_encode(['source' => $source->path($operation->payload['source_path']), 'source_sha256' => $operation->payload['source_sha256'], 'snapshot' => $snapshot ?? $operation->payload['snapshot'], 'draft_preview' => $draft], JSON_THROW_ON_ERROR))) {
            throw new RuntimeException('Cannot save preview input.');
        }
        $result = Process::path(base_path())->timeout($timeout ?? config('letters.render_timeout'))->run([PHP_BINARY, base_path('artisan'), 'letters:render-preview', $directory, '--no-interaction']);
        if ($result->exitCode() === 2) {
            throw ValidationException::withMessages(['preview' => 'DOCX draft tidak dapat dirender. Periksa isian dan templat.']);
        }
        if ($result->failed()) {
            throw new RuntimeException('Preview renderer failed.');
        }
        $path = $directory . '/preview.docx';
        if (!$disk->exists($path) || !$disk->exists($directory . '/result.json')) {
            throw new RuntimeException('Preview renderer returned no output.');
        }
        $usage = json_decode($disk->get($directory . '/result.json'), true, flags: JSON_THROW_ON_ERROR);
        $size = $disk->size($path);
        if ($size <= 0 || $size > config('letters.max_output_bytes') || !isset($usage['requirements'], $usage['limitations'])) {
            throw ValidationException::withMessages(['preview' => 'Hasil preview melewati batas atau tidak valid. Kurangi isian dan coba lagi.']);
        }

        return ['path' => $path, 'sha256' => hash_file('sha256', $disk->path($path)), 'size' => $size, 'usage' => $usage];
    }
}
