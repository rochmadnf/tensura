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
            $this->reject('Berkas tidak dapat dibuka sebagai dokumen Word. Buka di Word, simpan sebagai .docx, lalu unggah kembali.');
        }
        try {
            if ($zip->numFiles > config('letter-templates.zip.max_entries')) {
                $this->reject('Dokumen terlalu rumit untuk diproses. Kurangi gambar atau objek yang tidak diperlukan, lalu simpan dan unggah kembali.');
            }
            $names = [];
            $parts = [];
            $total = 0;
            $xmlTotal = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                if ($entry === false) {
                    $this->reject('Sebagian isi dokumen tidak dapat dibaca. Buka dan simpan ulang melalui Word, lalu unggah kembali.');
                }
                $name = $entry['name'];
                if (isset($names[$name]) || str_contains($name, '\\') || str_contains($name, "\0") || preg_match('~(?:^/|^[a-zA-Z]:|(?:^|/)\.\.(?:/|$)|(?:^|/)\.(?:/|$))~', $name)) {
                    $this->reject('Susunan berkas dokumen tidak aman atau rusak. Buat salinan baru melalui Simpan Sebagai di Word, lalu unggah kembali.');
                }
                $names[$name] = true;
                $system = $attributes = 0;
                $zip->getExternalAttributesIndex($index, $system, $attributes);
                if (($attributes >> 16 & 0170000) === 0120000 || ($entry['encryption_method'] ?? 0) !== 0) {
                    $this->reject('Dokumen terkunci atau menggunakan tautan berkas yang tidak didukung. Simpan salinan .docx tanpa kata sandi dan tanpa tautan berkas, lalu unggah kembali.');
                }
                $total += $entry['size'];
                if ($total > config('letter-templates.zip.max_uncompressed_bytes') || config('letter-templates.zip.max_compression_ratio') < $entry['size'] / max(1, $entry['comp_size'])) {
                    $this->reject('Isi dokumen terlalu besar untuk diproses dengan aman. Kurangi ukuran gambar atau isi dokumen, lalu unggah kembali.');
                }
                if (preg_match('~(?:vbaProject|activeX|embeddings|customUI)(?:/|\.|$)|\.(?:exe|dll|js|vbs|ps1|html|hta)$~i', $name)) {
                    $this->reject('Dokumen berisi makro, lampiran, atau objek aktif. Hapus komponen tersebut atau ubah menjadi gambar biasa, lalu simpan sebagai .docx.');
                }
                if (!str_ends_with(strtolower($name), '.xml') && !str_ends_with(strtolower($name), '.rels')) {
                    if (!str_ends_with($name, '/') && !preg_match('~^(?:(?:word/media|docProps)/[^/]+\.(?:png|jpe?g|gif|bmp|tiff?|emf|wmf)|word/fonts/[^/]+\.(?:odttf|ttf|otf)|word/printerSettings/[^/]+\.bin)$~iD', $name)) {
                        $this->reject('Ada objek atau jenis gambar yang belum didukung. Gunakan gambar PNG atau JPG yang disisipkan langsung dan hapus lampiran dokumen.');
                    }

                    continue;
                }
                $xmlTotal += $entry['size'];
                if ($entry['size'] > config('letter-templates.zip.max_xml_bytes') || $xmlTotal > config('letter-templates.zip.max_xml_total_bytes')) {
                    $this->reject('Bagian isi dokumen terlalu besar. Sederhanakan isi atau bagi menjadi dokumen yang lebih kecil, lalu unggah kembali.');
                }
                $xml = $zip->getFromIndex($index);
                if ($xml === false || strlen($xml) !== $entry['size'] || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
                    $this->reject('Susunan isi dokumen tidak dapat dibaca dengan aman. Buka dan simpan ulang sebagai .docx melalui Word, lalu unggah kembali.');
                }
                $previous = libxml_use_internal_errors(true);
                try {
                    $document = new DOMDocument();
                    $document->resolveExternals = false;
                    $document->substituteEntities = false;
                    if (!$document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
                        $this->reject('Susunan isi dokumen rusak. Buka dan simpan ulang sebagai .docx melalui Word, lalu unggah kembali.');
                    }
                    if ($document->doctype !== null) {
                        $this->reject('Dokumen berisi pengaturan isi yang tidak didukung. Salin isi surat ke dokumen Word baru dan simpan sebagai .docx.');
                    }
                } finally {
                    libxml_clear_errors();
                    libxml_use_internal_errors($previous);
                }
                $parts[$name] = $document;
            }
            if (!isset($parts['[Content_Types].xml'], $parts['_rels/.rels'], $parts['word/document.xml'])) {
                $this->reject('Berkas tidak memiliki isi dokumen Word yang lengkap. Simpan ulang sebagai .docx melalui Word atau gunakan dokumen baru.');
            }
            $types = new DOMXPath($parts['[Content_Types].xml']);
            $types->registerNamespace('ct', 'http://schemas.openxmlformats.org/package/2006/content-types');
            if ($types->query('/ct:Types/ct:Override[@PartName="/word/document.xml" and @ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"]')->length !== 1) {
                $this->reject('Format dokumen belum didukung. Gunakan Simpan Sebagai di Word dan pilih Dokumen Word (.docx) tanpa makro.');
            }
            foreach ($parts as $name => $document) {
                if ($name === '[Content_Types].xml' && preg_match('/macroEnabled|vbaProject|oleObject|activeX/i', $document->saveXML())) {
                    $this->reject('Dokumen berisi makro atau objek aktif. Hapus komponen tersebut, lalu simpan sebagai .docx tanpa makro.');
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
                    $this->reject('Dokumen mengambil isi secara otomatis dari berkas lain. Ubah isi tertaut menjadi teks atau gambar biasa, lalu unggah kembali.');
                }
                if ($xpath->query('//w:altChunk | //w:object | //w:control')->length > 0) {
                    $this->reject('Dokumen berisi lampiran, kontrol interaktif, atau sisipan dokumen lain. Ganti dengan teks atau gambar biasa, lalu unggah kembali.');
                }
            }
            if ($parts['word/document.xml']->documentElement?->namespaceURI !== self::WORD_NAMESPACE || $parts['word/document.xml']->documentElement?->localName !== 'document') {
                $this->reject('Format dokumen belum didukung. Buka melalui Word dan simpan kembali sebagai Dokumen Word (.docx).');
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
                    $this->reject('Kepala atau kaki halaman belum dapat diproses. Buka dan simpan ulang dokumen melalui Word, lalu unggah kembali.');
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
            $this->reject('Tautan antarbagian dokumen rusak. Buka dan simpan ulang melalui Word, lalu unggah kembali.');
        }
        foreach ($xpath->query('/r:Relationships/r:Relationship') as $relationship) {
            $target = rawurldecode($relationship->getAttribute('Target'));
            $type = $relationship->getAttribute('Type');
            if (preg_match('~/(?:oleObject|package|attachedTemplate|aFChunk|control)$~i', $type)) {
                $this->reject('Ada lampiran atau objek aktif yang ditautkan ke dokumen. Hapus tautan tersebut dan gunakan teks atau gambar biasa.');
            }
            if (strcasecmp($relationship->getAttribute('TargetMode'), 'External') === 0) {
                if (!str_ends_with($type, '/hyperlink') || !in_array(strtolower(parse_url($target, PHP_URL_SCHEME) ?? ''), ['http', 'https', 'mailto'], true)) {
                    $this->reject('Dokumen menggunakan gambar atau templat dari berkas lain. Sisipkan gambar langsung ke dokumen dan hapus tautan ke templat lain.');
                }

                continue;
            }
            if ($target === '' || str_contains($target, '\\') || str_contains($target, "\0") || preg_match('~^[a-zA-Z][a-zA-Z0-9+.-]*:~', $target)) {
                $this->reject('Dokumen memiliki tautan berkas yang tidak aman. Hapus tautan tersebut, lalu simpan dan unggah kembali dokumen.');
            }
            $base = $name === '_rels/.rels' ? '' : dirname(dirname($name)) . '/';
            $segments = [];
            foreach (explode('/', explode('#', str_starts_with($target, '/') ? ltrim($target, '/') : $base . $target)[0]) as $segment) {
                if ($segment === '..') {
                    if ($segments === []) {
                        $this->reject('Dokumen mengacu ke berkas di luar dokumen. Hapus tautan berkas tersebut dan sisipkan isi yang diperlukan secara langsung.');
                    }
                    array_pop($segments);
                } elseif ($segment !== '' && $segment !== '.') {
                    $segments[] = $segment;
                }
            }
            if (!isset($names[implode('/', $segments)])) {
                $this->reject('Ada gambar atau bagian dokumen yang hilang. Perbaiki bagian tersebut di Word, lalu simpan dan unggah kembali.');
            }
        }
        if ($name === '_rels/.rels' && $xpath->query('/r:Relationships/r:Relationship[@Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" and @Target="word/document.xml"]')->length !== 1) {
            $this->reject('Isi utama dokumen tidak dapat dikenali. Simpan ulang sebagai .docx melalui Word atau salin isi ke dokumen baru.');
        }
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['docx' => $message]);
    }
}
