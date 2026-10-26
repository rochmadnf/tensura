<?php

namespace App\Console\Commands;

use App\Services\Letter\RenderLetterDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RenderLetterPreviewCommand extends Command
{
    protected $signature = 'letters:render-preview {directory}';

    protected $description = 'Render an internal staged letter document within a bounded subprocess';

    public function handle(RenderLetterDocument $renderer): int
    {
        $directory = $this->argument('directory');
        $prefix = preg_quote(config('letters.paths.staging'), '~');
        if (!preg_match('~\A' . $prefix . '/[a-f0-9-]{36}/[a-f0-9-]{36}\z~D', $directory)) {
            return self::INVALID;
        }
        $disk = Storage::disk(config('letters.disk'));
        $input = json_decode($disk->get($directory . '/input.json'), true, flags: JSON_THROW_ON_ERROR);
        if (!is_file($input['source']) || hash_file('sha256', $input['source']) !== $input['source_sha256']) {
            return self::INVALID;
        }
        try {
            $usage = $renderer->render($input['source'], [...$input['snapshot'], 'draft_preview' => $input['draft_preview'] ?? true], $disk->path($directory . '/preview.docx'));
            if (!$disk->put($directory . '/result.json', json_encode($usage, JSON_THROW_ON_ERROR))) {
                throw new RuntimeException('Cannot save render manifest.');
            }

            return self::SUCCESS;
        } catch (ValidationException) {
            return self::INVALID;
        }
    }
}
