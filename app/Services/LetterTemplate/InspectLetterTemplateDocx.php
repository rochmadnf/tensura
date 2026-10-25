<?php

namespace App\Services\LetterTemplate;

use DOMDocument;
use DOMXPath;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class InspectLetterTemplateDocx
{
    public const string WORD_NAMESPACE = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** @return array<string, DOMDocument> */
    public function read(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
            $this->reject('Berkas bukan paket DOCX yang dapat dibaca.');
        }
        try {
            if ($zip->numFiles > config('letter-templates.zip.max_entries')) {
                $this->reject('DOCX memiliki terlalu banyak komponen ZIP.');
            }
            $names = [];
            $parts = [];
            $total = 0;
            $xmlTotal = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                if ($entry === false) {
                    $this->reject('Komponen ZIP tidak dapat dibaca.');
                }
                $name = $entry['name'];
                if (isset($names[$name]) || str_contains($name, '\\') || str_contains($name, "\0") || preg_match('~(?:^/|^[a-zA-Z]:|(?:^|/)\.\.(?:/|$)|(?:^|/)\.(?:/|$))~', $name)) {
                    $this->reject('DOCX mengandung path ZIP tidak aman atau duplikat.');
                }
                $names[$name] = true;
                $system = $attributes = 0;
                $zip->getExternalAttributesIndex($index, $system, $attributes);
                if (($attributes >> 16 & 0170000) === 0120000 || ($entry['encryption_method'] ?? 0) !== 0) {
                    $this->reject('DOCX dengan symlink atau enkripsi tidak didukung.');
                }
                $total += $entry['size'];
                if ($total > config('letter-templates.zip.max_uncompressed_bytes') || config('letter-templates.zip.max_compression_ratio') < $entry['size'] / max(1, $entry['comp_size'])) {
                    $this->reject('Ukuran dekompresi atau rasio kompresi DOCX melewati batas aman.');
                }
                if (preg_match('~(?:vbaProject|activeX|embeddings|customUI)(?:/|\.|$)|\.(?:exe|dll|js|vbs|ps1|html|hta)$~i', $name)) {
                    $this->reject('Macro, objek tertanam, dan komponen aktif tidak didukung.');
                }
                if (!str_ends_with(strtolower($name), '.xml') && !str_ends_with(strtolower($name), '.rels')) {
                    if (!str_ends_with($name, '/') && !preg_match('~^(?:(?:word/media|docProps)/[^/]+\.(?:png|jpe?g|gif|bmp|tiff?|emf|wmf)|word/fonts/[^/]+\.(?:odttf|ttf|otf)|word/printerSettings/[^/]+\.bin)$~iD', $name)) {
                        $this->reject('Komponen biner DOCX tidak didukung. Gunakan gambar tertanam biasa tanpa lampiran atau SVG.');
                    }

                    continue;
                }
                $xmlTotal += $entry['size'];
                if ($entry['size'] > config('letter-templates.zip.max_xml_bytes') || $xmlTotal > config('letter-templates.zip.max_xml_total_bytes')) {
                    $this->reject('Salah satu komponen XML DOCX terlalu besar.');
                }
                $xml = $zip->getFromIndex($index);
                if ($xml === false || strlen($xml) !== $entry['size'] || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
                    $this->reject('XML tidak dapat dibaca atau mengandung deklarasi entitas yang tidak diizinkan.');
                }
                $previous = libxml_use_internal_errors(true);
                try {
                    $document = new DOMDocument();
                    $document->resolveExternals = false;
                    $document->substituteEntities = false;
                    if (!$document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
                        $this->reject('Struktur XML DOCX tidak valid.');
                    }
                    if ($document->doctype !== null) {
                        $this->reject('Deklarasi DTD atau entitas XML tidak diizinkan.');
                    }
                } finally {
                    libxml_clear_errors();
                    libxml_use_internal_errors($previous);
                }
                $parts[$name] = $document;
            }
            if (!isset($parts['[Content_Types].xml'], $parts['_rels/.rels'], $parts['word/document.xml'])) {
                $this->reject('DOCX wajib memiliki content types, relasi paket, dan word/document.xml.');
            }
            $types = new DOMXPath($parts['[Content_Types].xml']);
            $types->registerNamespace('ct', 'http://schemas.openxmlformats.org/package/2006/content-types');
            if ($types->query('/ct:Types/ct:Override[@PartName="/word/document.xml" and @ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"]')->length !== 1) {
                $this->reject('Jenis dokumen utama harus DOCX Word biasa, tanpa macro.');
            }
            foreach ($parts as $name => $document) {
                if ($name === '[Content_Types].xml' && preg_match('/macroEnabled|vbaProject|oleObject|activeX/i', $document->saveXML())) {
                    $this->reject('Macro atau objek aktif tidak didukung.');
                }
                if (str_ends_with(strtolower($name), '.rels')) {
                    $this->checkRelationships($name, $document, $names);
                }
                $xpath = new DOMXPath($document);
                $xpath->registerNamespace('w', self::WORD_NAMESPACE);
                $instructions = '';
                foreach ($xpath->query('//w:instrText | //w:fldChar | //w:fldSimple/@w:instr') as $instruction) {
                    $instructions .= $instruction->localName === 'fldChar' ? ' ' : ($instruction instanceof \DOMAttr ? ' ' . $instruction->textContent . ' ' : $instruction->textContent);
                }
                if (preg_match('/\b(?:DDEAUTO|DDE|INCLUDETEXT|INCLUDEPICTURE|LINK|DATABASE)\b/i', $instructions)) {
                    $this->reject('Field aktif atau konten tertaut dalam DOCX tidak didukung.');
                }
                if ($xpath->query('//w:altChunk | //w:object | //w:control')->length > 0) {
                    $this->reject('Objek tertanam, kontrol aktif, dan altChunk tidak didukung.');
                }
            }
            if ($parts['word/document.xml']->documentElement?->namespaceURI !== self::WORD_NAMESPACE || $parts['word/document.xml']->documentElement?->localName !== 'document') {
                $this->reject('Namespace dokumen ini tidak didukung; simpan sebagai DOCX Word biasa.');
            }
            foreach (['header', 'footer'] as $kind) {
                $indexes = [];
                foreach (array_keys($parts) as $name) {
                    if (preg_match('~^word/' . $kind . '([0-9]+)\.xml$~D', $name, $matches)) {
                        $indexes[] = (int) $matches[1];
                    }
                }
                sort($indexes);
                if ($indexes !== [] && $indexes !== range(1, count($indexes))) {
                    $this->reject('Penamaan header/footer tidak didukung PHPWord; simpan ulang DOCX melalui Word.');
                }
            }

            return $parts;
        } finally {
            $zip->close();
        }
    }

    /** @param array<string, bool> $names */
    private function checkRelationships(string $name, DOMDocument $document, array $names): void
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');
        if ($document->documentElement?->namespaceURI !== 'http://schemas.openxmlformats.org/package/2006/relationships') {
            $this->reject('Namespace relasi DOCX tidak valid.');
        }
        foreach ($xpath->query('/r:Relationships/r:Relationship') as $relationship) {
            $target = rawurldecode($relationship->getAttribute('Target'));
            $type = $relationship->getAttribute('Type');
            if (preg_match('~/(?:oleObject|package|attachedTemplate|aFChunk|control)$~i', $type)) {
                $this->reject('Relasi komponen aktif tidak didukung.');
            }
            if (strcasecmp($relationship->getAttribute('TargetMode'), 'External') === 0) {
                if (!str_ends_with($type, '/hyperlink') || !in_array(strtolower(parse_url($target, PHP_URL_SCHEME) ?? ''), ['http', 'https', 'mailto'], true)) {
                    $this->reject('Konten eksternal DOCX tidak didukung; sematkan gambar dan hapus template tertaut.');
                }

                continue;
            }
            if ($target === '' || str_contains($target, '\\') || str_contains($target, "\0") || preg_match('~^[a-zA-Z][a-zA-Z0-9+.-]*:~', $target)) {
                $this->reject('Target relasi DOCX tidak aman.');
            }
            $base = $name === '_rels/.rels' ? '' : dirname(dirname($name)) . '/';
            $segments = [];
            foreach (explode('/', explode('#', str_starts_with($target, '/') ? ltrim($target, '/') : $base . $target)[0]) as $segment) {
                if ($segment === '..') {
                    if ($segments === []) {
                        $this->reject('Relasi DOCX keluar dari paket.');
                    }
                    array_pop($segments);
                } elseif ($segment !== '' && $segment !== '.') {
                    $segments[] = $segment;
                }
            }
            if (!isset($names[implode('/', $segments)])) {
                $this->reject('Relasi DOCX menunjuk komponen yang tidak tersedia.');
            }
        }
        if ($name === '_rels/.rels' && $xpath->query('/r:Relationships/r:Relationship[@Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" and @Target="word/document.xml"]')->length !== 1) {
            $this->reject('Relasi dokumen utama DOCX tidak didukung.');
        }
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['docx' => $message]);
    }
}
