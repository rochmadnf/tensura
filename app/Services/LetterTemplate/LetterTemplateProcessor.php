<?php

namespace App\Services\LetterTemplate;

use DOMDocument;
use DOMXPath;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpWord\TemplateProcessor;

class LetterTemplateProcessor extends TemplateProcessor
{
    public function markDraftNumber(): void
    {
        $mark = static function (string $xml): string {
            $document = new DOMDocument();
            $document->loadXML($xml, LIBXML_NONET);
            $xpath = new DOMXPath($document);
            $namespace = InspectLetterTemplateDocx::WORD_NAMESPACE;
            $xpath->registerNamespace('w', $namespace);
            foreach ($xpath->query('//w:r[w:t[contains(., "${nomor_surat")]]') as $run) {
                if (!preg_match('/\$\{nomor_surat(?:#\d+)?\}/', $run->textContent)) {
                    continue;
                }
                $properties = $xpath->query('w:rPr', $run)->item(0);
                if ($properties === null) {
                    $properties = $document->createElementNS($namespace, 'w:rPr');
                    $run->insertBefore($properties, $run->firstChild);
                }
                foreach ($xpath->query('w:color', $properties) as $color) {
                    $properties->removeChild($color);
                }
                $color = $document->createElementNS($namespace, 'w:color');
                $color->setAttributeNS($namespace, 'w:val', 'B45309');
                $properties->appendChild($color);
            }

            return $document->saveXML();
        };
        $this->tempDocumentMainPart = $mark($this->tempDocumentMainPart);
        $this->tempDocumentHeaders = array_map($mark, $this->tempDocumentHeaders);
        $this->tempDocumentFooters = array_map($mark, $this->tempDocumentFooters);
    }

    public function cloneRepeatedRow(string $placeholder, int $count): void
    {
        if ($this->cloneRepeatedBlock($placeholder, $count)) {
            return;
        }
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
        if ($this->cloneRepeatedBlock($placeholder, 0)) {
            return;
        }
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

    private function cloneRepeatedBlock(string $placeholder, int $count): bool
    {
        $parts = $this->renderedParts();
        $blocks = (new ReadRepeatedLetterBlocks())->read($parts);
        foreach ($blocks as $name => $block) {
            if (!in_array($placeholder, $block['placeholders'], true)) {
                continue;
            }
            $document = $parts['word/document.xml'];
            $rows = $block['rows'];
            $length = array_sum(array_map(fn(\DOMElement $row): int => strlen($document->saveXML($row)), $rows));
            if (strlen($this->tempDocumentMainPart) + $length * max(0, $count - 1) > config('letter-templates.zip.max_xml_bytes')) {
                throw ValidationException::withMessages(['test' => 'Isi blok hasil uji terlalu besar. Kurangi data pilihan.']);
            }
            $table = $rows[0]->parentNode;
            for ($index = 1; $index <= $count; $index++) {
                foreach ($rows as $position => $row) {
                    if ($index === $count && $block['separator'] && $position === count($rows) - 1) {
                        continue;
                    }
                    $copy = $row->cloneNode(true);
                    $xpath = new DOMXPath($document);
                    $xpath->registerNamespace('w', InspectLetterTemplateDocx::WORD_NAMESPACE);
                    foreach ($xpath->query('.//w:t', $copy) as $text) {
                        $value = str_replace(['${blok:' . $name . '}', '${/blok:' . $name . '}'], '', $text->textContent);
                        $text->textContent = preg_replace_callback('/\$\{([^}]+)\}/u', fn(array $match): string => '${' . $match[1] . '#' . $index . '}', $value);
                    }
                    $table->insertBefore($copy, $rows[0]);
                }
            }
            foreach ($rows as $row) {
                $table->removeChild($row);
            }
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('w', InspectLetterTemplateDocx::WORD_NAMESPACE);
            if ($xpath->query('w:tr', $table)->length === 0) {
                $table->parentNode->removeChild($table);
            }
            $this->tempDocumentMainPart = $document->saveXML();

            return true;
        }

        return false;
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

    /** @return array<string, DOMDocument> */
    public function renderedParts(): array
    {
        $parts = ['word/document.xml' => $this->tempDocumentMainPart];
        foreach ($this->tempDocumentHeaders as $index => $xml) {
            $parts['word/header' . $index . '.xml'] = $xml;
        }
        foreach ($this->tempDocumentFooters as $index => $xml) {
            $parts['word/footer' . $index . '.xml'] = $xml;
        }

        return array_map(function (string $xml): DOMDocument {
            $document = new DOMDocument();
            $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);

            return $document;
        }, $parts);
    }

    public function __destruct()
    {
        parent::__destruct();
        if (isset($this->tempDocumentFilename) && is_file($this->tempDocumentFilename)) {
            unlink($this->tempDocumentFilename);
        }
    }
}
