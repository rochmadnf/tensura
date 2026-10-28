<?php

namespace Tests\Feature;

use App\Models\MasterData\LetterTemplateSignerSlot;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\Letter\ConvertLetterPdf;
use App\Services\Letter\DocumentFontRegistry;
use App\Services\Letter\RenderLetterDocument;
use App\Services\LetterTemplate\InspectLetterTemplateDocx;
use App\Services\LetterTemplate\LetterTemplateProcessor;
use App\Services\LetterTemplate\ValidateLetterTemplateDocx;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

class LetterTemplateBlocksTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('counts')]
    public function test_vertical_blocks_keep_people_together_and_remove_only_the_last_separator(int $count): void
    {
        $source = $this->source(['${blok:pemohon}Nama : ${nama}', 'NIP : ${nip}', 'Jabatan : ${jabatan}', '${/blok:pemohon}']);
        $destination = tempnam(sys_get_temp_dir(), 'block-result-');
        $values = ['judul' => 'Surat tugas'];
        for ($index = 1; $index <= $count; $index++) {
            $values += ['nama#' . $index => 'Orang ' . $index, 'nip#' . $index => 'NIP ' . $index, 'jabatan#' . $index => 'Jabatan panjang ' . $index];
        }
        try {
            app(RenderLetterDocument::class)->render($source, ['schema_version' => 1, 'rows' => [['anchor' => 'nama', 'count' => $count]], 'values' => $values], $destination);
            $document = app(InspectLetterTemplateDocx::class)->read($destination)['word/document.xml'];
            $xpath = new \DOMXPath($document);
            $xpath->registerNamespace('w', InspectLetterTemplateDocx::WORD_NAMESPACE);
            $rows = $xpath->query('//w:tbl/w:tr');
            $this->assertSame($count === 0 ? 0 : $count * 4 - 1, $rows->length);
            $this->assertStringContainsString('Surat tugas', $document->textContent);
            $this->assertStringContainsString('Penandatangan tetap', $document->textContent);
            $this->assertStringNotContainsString('${', $document->textContent);
            for ($index = 1; $index <= $count; $index++) {
                $offset = ($index - 1) * 4;
                $this->assertSame('Nama : Orang ' . $index, $rows->item($offset)->textContent);
                $this->assertSame('NIP : NIP ' . $index, $rows->item($offset + 1)->textContent);
                $this->assertSame('Jabatan : Jabatan panjang ' . $index, $rows->item($offset + 2)->textContent);
                if ($index < $count) {
                    $this->assertSame('', $rows->item($offset + 3)->textContent);
                    $this->assertSame('240', $xpath->evaluate('string(w:trPr/w:trHeight/@w:val)', $rows->item($offset + 3)));
                }
            }
        } finally {
            unlink($source);
            unlink($destination);
        }
    }

    public static function counts(): array
    {
        return [[0], [1], [2], [3]];
    }

    public function test_closing_row_with_data_and_static_rows_outside_block_are_preserved(): void
    {
        $source = $this->source(['HEADER', '${blok:pemohon}Nama ${nama}', 'Jabatan ${jabatan}${/blok:pemohon}', 'FOOTER']);
        $output = tempnam(sys_get_temp_dir(), 'block-result-');
        try {
            app(RenderLetterDocument::class)->render($source, ['schema_version' => 1, 'rows' => [['anchor' => 'nama', 'count' => 2]], 'values' => ['judul' => 'Judul', 'nama#1' => 'A', 'nama#2' => 'B', 'jabatan#1' => 'X', 'jabatan#2' => 'Y']], $output);
            $text = app(InspectLetterTemplateDocx::class)->read($output)['word/document.xml']->textContent;
            $this->assertStringContainsString('HEADERNama AJabatan XNama BJabatan YFOOTER', $text);
        } finally {
            unlink($source);
            unlink($output);
        }
    }

    #[DataProvider('invalidBlocks')]
    public function test_invalid_block_layouts_are_rejected(array $rows): void
    {
        $version = $this->version();
        $source = $this->source($rows);
        try {
            try {
                $result = app(ValidateLetterTemplateDocx::class)->inspectPlaceholders($source, $version);
                $this->assertFalse($result['valid']);
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('docx', $exception->errors());
            }
        } finally {
            unlink($source);
        }
    }

    public static function invalidBlocks(): array
    {
        return [
            'missing close' => [['${blok:pemohon}${nama}']],
            'reversed' => [['${/blok:pemohon}', '${blok:pemohon}${nama}']],
            'nested' => [['${blok:pemohon}${nama}', '${blok:lain}${lain}', '${/blok:lain}', '${/blok:pemohon}']],
            'overlap' => [['${blok:pemohon}${nama}', '${blok:lain}${lain}', '${/blok:pemohon}', '${/blok:lain}']],
            'duplicate' => [['${blok:pemohon}${nama}${/blok:pemohon}', '${blok:pemohon}${nama}${/blok:pemohon}']],
            'outside' => [['${nama}', '${blok:pemohon}${nama}', '${/blok:pemohon}']],
            'mixed group' => [['${blok:pemohon}${nama}${lain}${/blok:pemohon}']],
            'unknown group' => [['${blok:tidak_ada}${nama}${/blok:tidak_ada}']],
        ];
    }

    public function test_markers_split_across_word_runs_validate_and_render(): void
    {
        $version = $this->version();
        $source = $this->source(['${blok:pemohon}${nama}', '${/blok:pemohon}']);
        $output = tempnam(sys_get_temp_dir(), 'block-result-');
        try {
            $zip = new ZipArchive();
            $zip->open($source);
            $zip->addFromString('word/document.xml', str_replace('blok:pemohon', 'blok:</w:t></w:r><w:r><w:t>pemohon', $zip->getFromName('word/document.xml')));
            $zip->close();
            $this->assertTrue(app(ValidateLetterTemplateDocx::class)->inspectPlaceholders($source, $version)['valid']);
            app(RenderLetterDocument::class)->render($source, ['schema_version' => 1, 'rows' => [['anchor' => 'nama', 'count' => 2]], 'values' => ['judul' => 'Judul', 'nama#1' => 'A', 'nama#2' => 'B']], $output);
            $text = app(InspectLetterTemplateDocx::class)->read($output)['word/document.xml']->textContent;
            $this->assertStringContainsString('AB', $text);
            $this->assertStringNotContainsString('blok:', $text);
        } finally {
            unlink($source);
            unlink($output);
        }
    }

    public function test_output_limit_counts_the_entire_block_before_cloning(): void
    {
        $source = $this->source(['${blok:pemohon}${nama}', str_repeat('panjang', 200), '${/blok:pemohon}']);
        $output = tempnam(sys_get_temp_dir(), 'block-result-');
        config(['letter-templates.zip.max_xml_bytes' => 8000]);
        try {
            $this->expectException(ValidationException::class);
            app(RenderLetterDocument::class)->render($source, ['schema_version' => 1, 'rows' => [['anchor' => 'nama', 'count' => 100]], 'values' => []], $output);
        } finally {
            unlink($source);
            unlink($output);
        }
    }

    #[DataProvider('unsupportedStructures')]
    public function test_rejects_cross_table_paragraph_header_nested_table_and_vertical_merge_blocks(string $kind): void
    {
        $version = $this->version();
        $source = $this->source(['${blok:pemohon}${nama}', '${/blok:pemohon}']);
        try {
            $zip = new ZipArchive();
            $zip->open($source);
            $xml = $zip->getFromName('word/document.xml');
            $w = InspectLetterTemplateDocx::WORD_NAMESPACE;
            $body = match ($kind) {
                'cross-table' => '<w:tbl><w:tr><w:tc><w:p><w:r><w:t>${blok:pemohon}${nama}</w:t></w:r></w:p></w:tc></w:tr></w:tbl><w:tbl><w:tr><w:tc><w:p><w:r><w:t>${/blok:pemohon}</w:t></w:r></w:p></w:tc></w:tr></w:tbl>',
                'paragraph' => '<w:p><w:r><w:t>${blok:pemohon}${nama}${/blok:pemohon}</w:t></w:r></w:p>',
                'nested-table' => '<w:tbl><w:tr><w:tc><w:tbl><w:tr><w:tc><w:p><w:r><w:t>${blok:pemohon}${nama}${/blok:pemohon}</w:t></w:r></w:p></w:tc></w:tr></w:tbl></w:tc></w:tr></w:tbl>',
                'merge' => '<w:tbl><w:tr><w:tc><w:tcPr><w:vMerge w:val="restart"/></w:tcPr><w:p><w:r><w:t>${blok:pemohon}${nama}${/blok:pemohon}</w:t></w:r></w:p></w:tc></w:tr></w:tbl>',
                'split-paragraph' => '<w:tbl><w:tr><w:tc><w:p><w:r><w:t>${blok:</w:t></w:r></w:p><w:p><w:r><w:t>pemohon}${nama}${/blok:pemohon}</w:t></w:r></w:p></w:tc></w:tr></w:tbl>',
                'header' => '<w:p><w:r><w:t>${judul}</w:t></w:r></w:p>',
            };
            $zip->addFromString('word/document.xml', '<w:document xmlns:w="' . $w . '"><w:body>' . $body . '</w:body></w:document>');
            if ($kind === 'header') {
                $zip->addFromString('word/header1.xml', '<w:hdr xmlns:w="' . $w . '"><w:tbl><w:tr><w:tc><w:p><w:r><w:t>${blok:pemohon}${nama}${/blok:pemohon}</w:t></w:r></w:p></w:tc></w:tr></w:tbl></w:hdr>');
            }
            $zip->close();
            $this->expectException(ValidationException::class);
            app(ValidateLetterTemplateDocx::class)->inspectPlaceholders($source, $version);
        } finally {
            unlink($source);
        }
    }

    public static function unsupportedStructures(): array
    {
        return array_map(fn(string $kind): array => [$kind], ['cross-table', 'paragraph', 'nested-table', 'merge', 'split-paragraph', 'header']);
    }

    public function test_multiple_independent_blocks_and_horizontal_rows_keep_static_content(): void
    {
        $version = $this->version();
        $source = $this->source(['HEADER', '${blok:pemohon}${nama}', 'Jabatan ${jabatan}', '${/blok:pemohon}', '${lain}', 'FOOTER']);
        $output = tempnam(sys_get_temp_dir(), 'block-result-');
        try {
            $this->assertTrue(app(ValidateLetterTemplateDocx::class)->inspectPlaceholders($source, $version)['valid']);
            app(RenderLetterDocument::class)->render($source, ['schema_version' => 1, 'rows' => [['anchor' => 'nama', 'count' => 0], ['anchor' => 'lain', 'count' => 2]], 'values' => ['judul' => 'Judul', 'lain#1' => 'A', 'lain#2' => 'B']], $output);
            $text = app(InspectLetterTemplateDocx::class)->read($output)['word/document.xml']->textContent;
            $this->assertStringContainsString('HEADERABFOOTER', $text);
            $this->assertStringNotContainsString('Jabatan', $text);
        } finally {
            unlink($source);
            unlink($output);
        }
    }

    public function test_prepared_user_template_renders_one_to_three_people_and_converts_to_pdf(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'prepared-block-');
        $directory = 'letters/staging/prepared-block-' . Str::uuid();
        $disk = Storage::fake('local');
        try {
            $this->restoreTemplateFixture($source);
            $processor = new LetterTemplateProcessor($source);
            $processor->saveAs($source);
            unset($processor);
            $zip = new ZipArchive();
            $zip->open($source);
            $document = new \DOMDocument();
            $document->loadXML($zip->getFromName('word/document.xml'), LIBXML_NONET);
            $xpath = new \DOMXPath($document);
            $w = InspectLetterTemplateDocx::WORD_NAMESPACE;
            $xpath->registerNamespace('w', $w);
            $row = $xpath->query('//w:tr[.//w:t[contains(., "${pemohon_nama}")]]')->item(0);
            $first = $xpath->query('.//w:t', $row)->item(0);
            $first->textContent = '${blok:pemohon}' . $first->textContent;
            $closing = $document->createElementNS($w, 'w:tr');
            $properties = $document->createElementNS($w, 'w:trPr');
            $height = $document->createElementNS($w, 'w:trHeight');
            $height->setAttributeNS($w, 'w:val', '240');
            $properties->appendChild($height);
            $closing->appendChild($properties);
            for ($i = 0; $i < 3; $i++) {
                $cell = $document->createElementNS($w, 'w:tc');
                $paragraph = $document->createElementNS($w, 'w:p');
                $run = $document->createElementNS($w, 'w:r');
                $text = $document->createElementNS($w, 'w:t');
                $text->textContent = $i === 0 ? '${/blok:pemohon}' : '';
                $run->appendChild($text);
                $paragraph->appendChild($run);
                $cell->appendChild($paragraph);
                $closing->appendChild($cell);
            }
            $row->parentNode->appendChild($closing);
            $zip->addFromString('word/document.xml', $document->saveXML());
            $zip->close();
            $version = LetterTemplateVersion::factory()->create();
            $variable = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'pemohon', 'type' => 4, 'option_source' => 2, 'is_multiple' => true]);
            $columns = ['nama' => 'name', 'nip' => 'nip', 'pangkat' => 'rank', 'golongan' => 'grade', 'jabatan' => 'job_title'];
            foreach ($columns as $suffix => $attribute) {
                $variable->columns()->create(['source_attribute' => $attribute, 'placeholder' => 'pemohon_' . $suffix, 'sort_order' => array_search($suffix, array_keys($columns), true)]);
            }
            foreach (['nama_kegiatan', 'waktu_kegiatan', 'lokasi_kegiatan'] as $name) {
                LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => $name]);
            }
            LetterTemplateSignerSlot::factory()->for($version, 'version')->create();
            $inspection = app(ValidateLetterTemplateDocx::class)->inspectPlaceholders($source, $version);
            $this->assertTrue($inspection['valid'], implode(' ', $inspection['errors']));
            $values = array_fill_keys(array_filter($inspection['placeholders'], fn(string $name): bool => !str_contains($name, 'blok:') && !str_starts_with($name, 'pemohon_')), 'Contoh');
            $values['nomor_surat'] = '445/XX.XX/PKM-KMJ/X/2026';
            for ($count = 1; $count <= 3; $count++) {
                for ($i = 1; $i <= $count; $i++) {
                    foreach ($columns as $suffix => $attribute) {
                        $values['pemohon_' . $suffix . '#' . $i] = match ($suffix) {
                            'nama' => 'Pegawai Contoh ' . $i,
                            'jabatan' => 'Penanggung Jawab Pelayanan Kesehatan Masyarakat Wilayah Kecamatan ' . $i,
                            default => strtoupper($suffix) . ' ' . $i,
                        };
                    }
                }
                $output = sys_get_temp_dir() . '/tensura-issue1-' . $count . '.docx';
                $usage = app(RenderLetterDocument::class)->render($source, ['schema_version' => 1, 'draft_preview' => true, 'rows' => [['anchor' => 'pemohon_nama', 'count' => $count]], 'values' => $values], $output);
                $rendered = app(InspectLetterTemplateDocx::class)->read($output)['word/document.xml'];
                $query = new \DOMXPath($rendered);
                $query->registerNamespace('w', $w);
                $this->assertSame($count * 5 - 1, $query->query('(//w:tbl)[2]/w:tr')->length);
                $this->assertStringNotContainsString('blok:', $rendered->textContent);
                for ($i = 1; $i <= $count; $i++) {
                    $this->assertStringContainsString('Pegawai Contoh ' . $i, $rendered->textContent);
                    $this->assertSame(':', trim($query->query('(//w:tbl)[2]/w:tr')->item(($i - 1) * 5)->getElementsByTagNameNS($w, 'tc')->item(1)->textContent));
                }
                if ($count === 3) {
                    $profile = app(DocumentFontRegistry::class)->conversionProfile($usage['requirements']);
                    $pdf = app(ConvertLetterPdf::class)->convertSource($directory, file_get_contents($output), hash_file('sha256', $output), true, $profile);
                    copy($disk->path($pdf['path']), sys_get_temp_dir() . '/tensura-issue1-3.pdf');
                    $text = Process::run(['/usr/bin/pdftotext', '-layout', $disk->path($pdf['path']), '-']);
                    $this->assertTrue($text->successful());
                    for ($i = 1; $i <= 3; $i++) {
                        $this->assertStringContainsString('Pegawai Contoh ' . $i, $text->output());
                    }
                    $this->assertStringNotContainsString('blok:', $text->output());
                }
            }
        } finally {
            unlink($source);
            $disk->deleteDirectory($directory);
        }
    }

    private function restoreTemplateFixture(string $destination): void
    {
        $fixture = new \DOMDocument();
        $this->assertTrue($fixture->load(base_path('tests/Fixtures/Letters/template.xml'), LIBXML_NONET));
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        try {
            foreach ($fixture->getElementsByTagName('part') as $part) {
                $name = $part->getAttribute('name');
                $encoding = $part->getAttribute('encoding');
                $contents = $encoding === 'base64' ? base64_decode($part->textContent, true) : $part->textContent;
                $this->assertNotFalse($contents, $name);
                $this->assertSame($part->getAttribute('sha256'), hash('sha256', $contents), $name);
                $this->assertTrue($encoding === 'directory' ? $zip->addEmptyDir($name) : $zip->addFromString($name, $contents));
            }
        } finally {
            $zip->close();
        }
    }

    private function version(): LetterTemplateVersion
    {
        $version = LetterTemplateVersion::factory()->create();
        LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'judul']);
        foreach (['pemohon' => ['nama', 'nip', 'jabatan'], 'kedua' => ['lain']] as $name => $columns) {
            $variable = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => $name, 'type' => 4, 'option_source' => 2, 'is_multiple' => true]);
            foreach ($columns as $index => $placeholder) {
                $variable->columns()->create(['placeholder' => $placeholder, 'source_attribute' => 'name', 'sort_order' => $index]);
            }
        }

        return $version;
    }

    private function source(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'letter-block-');
        $escaping = Settings::isOutputEscapingEnabled();
        Settings::setOutputEscapingEnabled(true);
        try {
            $word = new PhpWord();
            $section = $word->addSection();
            $section->addText('${judul}');
            $table = $section->addTable(['borderSize' => 0]);
            foreach ($rows as $row) {
                $table->addRow(240);
                $table->addCell(5000)->addText($row);
            }
            $section->addText('Penandatangan tetap');
            IOFactory::createWriter($word, 'Word2007')->save($path);
        } finally {
            Settings::setOutputEscapingEnabled($escaping);
        }

        return $path;
    }
}
