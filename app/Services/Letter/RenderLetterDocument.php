<?php

namespace App\Services\Letter;

use App\Services\LetterTemplate\InspectLetterTemplateDocx;
use App\Services\LetterTemplate\LetterTemplateProcessor;
use Illuminate\Validation\ValidationException;

class RenderLetterDocument
{
    public function __construct(private InspectLetterTemplateDocx $inspector, private InspectDocumentFonts $fonts) {}

    /**
     * @param  array{schema_version: int, values: array<string, string>, rows: list<array{anchor: string, count: int}>}  $snapshot
     * @return array{requirements: list<array{family: string, bold: bool, italic: bool}>, limitations: list<string>}
     */
    public function render(string $source, array $snapshot, string $destination): array
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
            if ($snapshot['draft_preview'] ?? false) {
                $processor->markDraftNumber();
            }
            $processor->fillLiteralValues($snapshot['values']);
            $parts = array_replace($this->inspector->read($source), $processor->renderedParts());
            $fontUsage = $this->fonts->inspect($parts);
            $processor->saveAs($destination);

            return $fontUsage;
        } finally {
            unset($processor);
        }
    }
}
