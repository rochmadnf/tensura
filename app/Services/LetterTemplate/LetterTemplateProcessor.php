<?php

namespace App\Services\LetterTemplate;

use DOMDocument;
use DOMXPath;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpWord\TemplateProcessor;

class LetterTemplateProcessor extends TemplateProcessor
{
    public function cloneRepeatedRow(string $placeholder, int $count): void
    {
        $position = strpos($this->tempDocumentMainPart, '${' . $placeholder . '}');
        if ($position === false) {
            throw ValidationException::withMessages(['test' => 'Baris templat tidak ditemukan. Validasi ulang DOCX.']);
        }
        $length = $this->findRowEnd($position) - $this->findRowStart($position);
        if (strlen($this->tempDocumentMainPart) + $length * ($count - 1) > config('letter-templates.zip.max_xml_bytes')) {
            throw ValidationException::withMessages(['test' => 'Isi tabel hasil uji terlalu besar. Kurangi data pilihan.']);
        }
        $this->cloneRow($placeholder, $count);
    }

    public function deleteEmptyTableRow(string $placeholder): void
    {
        $this->deleteRow($placeholder);
        $document = new DOMDocument();
        $document->loadXML($this->tempDocumentMainPart, LIBXML_NONET);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', InspectLetterTemplateDocx::WORD_NAMESPACE);
        foreach ($xpath->query('//w:tbl[not(w:tr)]') as $table) {
            $table->parentNode->removeChild($table);
        }
        $this->tempDocumentMainPart = $document->saveXML();
    }

    /** @param array<string, string> $values */
    public function fillLiteralValues(array $values): void
    {
        $replace = function (string $part) use ($values): string {
            $size = strlen($part);

            return preg_replace_callback('/\$\{([^}]+)\}/u', function (array $match) use ($values, &$size): string {
                $name = $match[1];
                $base = preg_replace('/#\d+$/', '', $name);
                if (!array_key_exists($name, $values) && !array_key_exists($base, $values)) {
                    throw ValidationException::withMessages(['test' => "Variabel {$name} tidak dapat diisi. Periksa Nama Variabel pada Konfigurasi Formulir Surat dan dokumen, lalu jalankan validasi ulang."]);
                }
                $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $values[$name] ?? $values[$base]);
                $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

                $replacement = $this->replaceCarriageReturns($escaped);
                $size += strlen($replacement) - strlen($match[0]);
                if ($size > config('letter-templates.zip.max_xml_bytes')) {
                    throw ValidationException::withMessages(['test' => 'Isi hasil uji terlalu besar. Kurangi panjang isian atau data pilihan.']);
                }

                return $replacement;
            }, $part);
        };
        $this->tempDocumentMainPart = $replace($this->tempDocumentMainPart);
        $this->tempDocumentHeaders = array_map($replace, $this->tempDocumentHeaders);
        $this->tempDocumentFooters = array_map($replace, $this->tempDocumentFooters);
        $total = strlen($this->tempDocumentMainPart) + array_sum(array_map('strlen', $this->tempDocumentHeaders)) + array_sum(array_map('strlen', $this->tempDocumentFooters));
        if ($total > config('letter-templates.zip.max_xml_total_bytes')) {
            throw ValidationException::withMessages(['test' => 'Total isi hasil uji melewati batas. Kurangi data contoh.']);
        }
    }

    public function __destruct()
    {
        parent::__destruct();
        if (isset($this->tempDocumentFilename) && is_file($this->tempDocumentFilename)) {
            unlink($this->tempDocumentFilename);
        }
    }
}
