<?php

namespace App\Services\Letter;

use App\Services\LetterTemplate\LetterTemplateProcessor;
use Illuminate\Validation\ValidationException;

class RenderLetterDocument
{
    /** @param array{schema_version: int, values: array<string, string>, rows: list<array{anchor: string, count: int}>} $snapshot */
    public function render(string $source, array $snapshot, string $destination): void
    {
        if (($snapshot['schema_version'] ?? null) !== 1) {
            throw ValidationException::withMessages(['test' => 'Versi snapshot dokumen belum didukung.']);
        }
        $processor = new LetterTemplateProcessor($source);
        try {
            foreach ($snapshot['rows'] as $row) {
                if ($row['count'] === 0) {
                    $processor->deleteEmptyTableRow($row['anchor']);
                } else {
                    $processor->cloneRepeatedRow($row['anchor'], $row['count']);
                }
            }
            $processor->fillLiteralValues($snapshot['values']);
            $processor->saveAs($destination);
        } finally {
            unset($processor);
        }
    }
}
