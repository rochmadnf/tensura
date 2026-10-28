<?php

namespace App\Services\LetterTemplate;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Validation\ValidationException;

class ReadRepeatedLetterBlocks
{
    /**
     * @param  array<string, DOMDocument>  $parts
     * @param  ?array<string, string>  $owners  Placeholder to multiple-variable name.
     * @return array<string, array{rows: list<DOMElement>, placeholders: list<string>, separator: bool, start: int, end: int}>
     */
    public function read(array $parts, ?array $owners = null): array
    {
        $blocks = [];
        $tokens = [];
        $open = null;
        foreach ($parts as $part => $document) {
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('w', InspectLetterTemplateDocx::WORD_NAMESPACE);
            foreach ($xpath->query('//w:p') as $paragraph) {
                $text = '';
                foreach ($xpath->query('.//w:t | .//w:tab | .//w:br | .//w:cr', $paragraph) as $node) {
                    if ($this->ancestor($node, 'p')?->isSameNode($paragraph)) {
                        $text .= $node->localName === 't' ? $node->textContent : "\n";
                    }
                }
                preg_match_all('/\$\{([^}]+)\}/u', $text, $matches);
                foreach ($matches[1] as $name) {
                    $row = $this->ancestor($paragraph, 'tr');
                    $table = $this->ancestor($paragraph, 'tbl');
                    $position = count($tokens);
                    if ($position >= config('letter-templates.placeholders.max_occurrences')) {
                        $this->reject('Jumlah penggunaan variabel dalam dokumen terlalu banyak. Kurangi variabel yang berulang, lalu unggah kembali.');
                    }
                    $tokens[] = ['name' => $name, 'row' => $row, 'part' => $part];
                    if (!str_contains($name, 'blok:')) {
                        continue;
                    }
                    if (!preg_match('/\A(\/?)blok:([a-z][a-z0-9_]*)\z/', $name, $marker)) {
                        $this->reject('Format penanda blok tidak sah. Gunakan ${blok:nama_variabel} dan ${/blok:nama_variabel}.');
                    }
                    $group = $marker[2];
                    if ($part !== 'word/document.xml' || $row === null || $table === null || $this->ancestor($table, 'tbl') !== null || !$row->parentNode->isSameNode($table)) {
                        $this->reject('Penanda blok harus berada dalam tabel di isi surat, tanpa tabel bersarang.');
                    }
                    if ($marker[1] === '') {
                        if ($open !== null || isset($blocks[$group])) {
                            $this->reject('Blok tidak boleh bersarang, bertumpang tindih, atau memakai lebih dari satu pasangan per kelompok.');
                        }
                        $open = ['name' => $group, 'row' => $row, 'table' => $table, 'position' => $position];
                    } else {
                        if ($open === null || $open['name'] !== $group || !$open['table']->isSameNode($table)) {
                            $this->reject('Penanda awal dan akhir blok wajib berpasangan, berurutan, dan berada dalam satu tabel.');
                        }
                        $rows = [];
                        for ($current = $open['row']; $current !== null; $current = $current->nextSibling) {
                            if ($current instanceof DOMElement && $current->localName === 'tr') {
                                if ($xpath->query('.//w:vMerge | .//w:tbl', $current)->length > 0) {
                                    $this->reject('Blok berulang tidak mendukung gabungan sel vertikal atau tabel bersarang.');
                                }
                                $rows[] = $current;
                            }
                            if ($current->isSameNode($row)) {
                                break;
                            }
                        }
                        $names = array_column(array_slice($tokens, $open['position'] + 1, $position - $open['position'] - 1), 'name');
                        $remaining = str_replace('${/blok:' . $group . '}', '', $row->textContent);
                        $separator = count($rows) > 1 && preg_match('/\A[\s\x{00a0}]*\z/u', $remaining) === 1
                            && $xpath->query('.//w:drawing | .//w:pict | .//w:object | .//w:fldChar | .//w:fldSimple | .//w:instrText | .//w:sym | .//w:br | .//w:cr | .//w:altChunk | .//w:footnoteReference | .//w:endnoteReference', $row)->length === 0;
                        $blocks[$group] = ['rows' => $rows, 'placeholders' => $names, 'separator' => $separator, 'start' => $open['position'], 'end' => $position];
                        $open = null;
                    }
                }
            }
        }
        if ($open !== null) {
            $this->reject('Penanda akhir blok belum ditemukan. Pasangkan setiap penanda awal dengan penanda akhir.');
        }
        if ($owners !== null) {
            foreach ($blocks as $group => $block) {
                if (!in_array($group, $owners, true) || !array_intersect($block['placeholders'], array_keys($owners, $group, true))) {
                    $this->reject("Blok {$group} harus memakai nama variabel pilihan lebih dari satu dan memuat placeholder kelompok tersebut.");
                }
            }
            foreach ($tokens as $position => $token) {
                $owner = $owners[$token['name']] ?? null;
                foreach ($blocks as $group => $block) {
                    $inside = $position > $block['start'] && $position < $block['end'];
                    if (($owner === $group && !$inside) || ($inside && $owner !== null && $owner !== $group)) {
                        $this->reject('Semua placeholder kelompok harus berada di dalam bloknya dan tidak boleh bercampur kelompok lain.');
                    }
                    if (!$inside && $owner !== $group && $token['row'] !== null && collect($block['rows'])->contains(fn(DOMElement $row): bool => $row->isSameNode($token['row'])) && $owner !== null) {
                        $this->reject('Satu baris blok tidak boleh dipakai oleh kelompok berulang lain.');
                    }
                }
            }
        }

        return $blocks;
    }

    private function ancestor(DOMNode $node, string $name): ?DOMElement
    {
        for ($parent = $node->parentNode; $parent !== null; $parent = $parent->parentNode) {
            if ($parent instanceof DOMElement && $parent->namespaceURI === InspectLetterTemplateDocx::WORD_NAMESPACE && $parent->localName === $name) {
                return $parent;
            }
        }

        return null;
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['docx' => $message]);
    }
}
