<?php

namespace Tests\Feature;

use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\Letter\DocumentFontRegistry;
use App\Services\Letter\InspectDocumentFonts;
use App\Services\Letter\RenderLetterDocument;
use App\Services\LetterTemplate\InspectLetterTemplateDocx;
use App\Services\LetterTemplate\LetterTemplateTestEvidence;
use App\Services\LetterTemplate\LetterTemplateVersionState;
use Database\Factories\UserFactory;
use DOMDocument;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use Tests\TestCase;

class LetterTemplateFontsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_effective_run_styles_theme_table_and_headers_exclude_unused_font_table_and_styles(): void
    {
        $w = InspectLetterTemplateDocx::WORD_NAMESPACE;
        $a = 'http://schemas.openxmlformats.org/drawingml/2006/main';
        $parts = [
            'word/document.xml' => $this->xml('<w:document xmlns:w="' . $w . '"><w:body>
                <w:p><w:r><w:t>Default font</w:t></w:r></w:p>
                <w:p><w:pPr><w:pStyle w:val="Child"/></w:pPr><w:r><w:t>Inherited theme</w:t></w:r></w:p>
                <w:p><w:pPr><w:pStyle w:val="Child"/></w:pPr><w:r><w:rPr><w:rFonts w:ascii="Direct Font"/><w:b w:val="0"/></w:rPr><w:t>Direct override</w:t></w:r></w:p>
                <w:tbl><w:tblPr><w:tblStyle w:val="Table"/></w:tblPr><w:tr><w:tc><w:p><w:r><w:t>Table text</w:t></w:r></w:p></w:tc></w:tr></w:tbl>
            </w:body></w:document>'),
            'word/header1.xml' => $this->xml('<w:hdr xmlns:w="' . $w . '"><w:p><w:r><w:rPr><w:rFonts w:ascii="Header Font"/><w:i/></w:rPr><w:t>Header</w:t></w:r></w:p></w:hdr>'),
            'word/footer1.xml' => $this->xml('<w:ftr xmlns:w="' . $w . '"><w:p><w:r><w:rPr><w:rStyle w:val="Character"/></w:rPr><w:t>Footer</w:t></w:r></w:p></w:ftr>'),
            'word/styles.xml' => $this->xml('<w:styles xmlns:w="' . $w . '">
                <w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Default Font" w:hAnsi="Default Font"/></w:rPr></w:rPrDefault></w:docDefaults>
                <w:style w:type="paragraph" w:styleId="Base"><w:rPr><w:rFonts w:asciiTheme="majorHAnsi"/><w:b/></w:rPr></w:style>
                <w:style w:type="paragraph" w:styleId="Child"><w:basedOn w:val="Base"/></w:style>
                <w:style w:type="character" w:styleId="Character"><w:rPr><w:rFonts w:ascii="Character Font"/></w:rPr></w:style>
                <w:style w:type="table" w:styleId="Table"><w:rPr><w:rFonts w:ascii="Table Font"/></w:rPr></w:style>
                <w:style w:type="paragraph" w:styleId="Unused"><w:rPr><w:rFonts w:ascii="Unused Font"/></w:rPr></w:style>
            </w:styles>'),
            'word/theme/theme1.xml' => $this->xml('<a:theme xmlns:a="' . $a . '"><a:themeElements><a:fontScheme name="Theme"><a:majorFont><a:latin typeface="Theme Font"/></a:majorFont></a:fontScheme></a:themeElements></a:theme>'),
            'word/fontTable.xml' => $this->xml('<w:fonts xmlns:w="' . $w . '"><w:font w:name="Unused Font Table"/></w:fonts>'),
        ];
        $result = app(InspectDocumentFonts::class)->inspect($parts);
        $fonts = collect($result['requirements'])->keyBy('family');
        $this->assertEqualsCanonicalizing(['Default Font', 'Theme Font', 'Direct Font', 'Header Font', 'Character Font', 'Table Font'], $fonts->keys()->all());
        $this->assertTrue($fonts['Theme Font']['bold']);
        $this->assertFalse($fonts['Direct Font']['bold']);
        $this->assertTrue($fonts['Header Font']['italic']);
    }

    public function test_theme_script_fonts_and_explicit_limitations_for_unsupported_or_unknown_formatting(): void
    {
        $w = InspectLetterTemplateDocx::WORD_NAMESPACE;
        $a = 'http://schemas.openxmlformats.org/drawingml/2006/main';
        $parts = ['word/document.xml' => $this->xml('<w:document xmlns:w="' . $w . '" xmlns:a="' . $a . '"><w:body>
            <w:p><w:r><w:rPr><w:rFonts w:eastAsiaTheme="minorEastAsia"/><w:lang w:eastAsia="ja-JP"/></w:rPr><w:t>日本語</w:t></w:r></w:p>
            <w:p><w:r><w:t>No explicit font</w:t></w:r></w:p>
            <w:p><w:pPr><w:pStyle w:val="Cycle"/><w:numPr/></w:pPr><w:r><w:t>Style cycle</w:t></w:r></w:p>
            <a:r><a:t>Drawing text</a:t></a:r>
            </w:body></w:document>'),
            'word/theme/theme1.xml' => $this->xml('<a:theme xmlns:a="' . $a . '"><a:themeElements><a:fontScheme><a:minorFont><a:ea typeface=""/><a:font script="Jpan" typeface="Japanese Font"/></a:minorFont></a:fontScheme></a:themeElements></a:theme>'),
            'word/styles.xml' => $this->xml('<w:styles xmlns:w="' . $w . '"><w:style w:styleId="Cycle"><w:basedOn w:val="Cycle"/></w:style></w:styles>')];
        $result = app(InspectDocumentFonts::class)->inspect($parts);
        $this->assertSame([['family' => 'Japanese Font', 'bold' => false, 'italic' => false]], $result['requirements']);
        $this->assertCount(4, $result['limitations']);
    }

    public function test_missing_font_warnings_do_not_block_generate_confirm_or_publish_and_word_keeps_font(): void
    {
        Storage::fake('local');
        $this->actingAs(UserFactory::new()->create());
        $registry = $this->partialMock(DocumentFontRegistry::class);
        $registry->shouldReceive('inventory')->andReturn(['available' => false, 'faces' => [], 'warning' => 'Inventory tidak tersedia.']);
        $version = LetterTemplateVersion::factory()->create();
        $original = $this->source($version);
        $version->refresh();
        $this->assertTrue($version->validation_result['valid']);
        $this->assertFalse($version->validation_result['fonts']['available']);
        $this->assertContains('Missing Example Font', array_column($version->validation_result['fonts']['requirements'], 'family'));
        $this->generate($version);
        $version->refresh();
        $token = app(LetterTemplateTestEvidence::class)->token($version);
        $this->post(route('lt.test.confirm', [$version->template->uuid, $version->uuid]), ['test_token' => $token, 'confirmed' => true])->assertSessionHasNoErrors();
        $this->post(route('lt.versions.publish', [$version->template->uuid, $version->uuid]), ['test_token' => $token, 'version_token' => app(LetterTemplateVersionState::class)->token($version->fresh()), 'confirmed' => true])->assertSessionHasNoErrors();
        $this->assertSame(LetterTemplateVersionStatusEnum::PUBLISHED, $version->fresh()->status);
        $this->assertSame($original, Storage::disk('local')->get($version->source_path));
        $xml = app(InspectLetterTemplateDocx::class)->read(Storage::disk('local')->path($version->test_path))['word/document.xml']->saveXML();
        $this->assertStringContainsString('Missing Example Font', $xml);
    }

    public function test_relevant_font_change_stales_preview_while_unrelated_change_keeps_confirmation(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('face.ttf', 'font bytes for hashing');
        $faces = [];
        $registry = $this->partialMock(DocumentFontRegistry::class);
        $registry->shouldReceive('inventory')->andReturnUsing(function () use (&$faces): array { return ['available' => true, 'faces' => $faces, 'warning' => null]; });
        $this->actingAs(UserFactory::new()->create());
        $version = LetterTemplateVersion::factory()->create();
        $this->source($version);
        $this->generate($version);
        $version->refresh();
        $token = app(LetterTemplateTestEvidence::class)->token($version);
        $this->post(route('lt.test.confirm', [$version->template->uuid, $version->uuid]), ['test_token' => $token, 'confirmed' => true])->assertSessionHasNoErrors();
        $face = ['family' => 'Unrelated Font', 'aliases' => ['Unrelated Font'], 'style' => 'Regular', 'weight' => 80, 'bold' => false, 'italic' => false, 'path' => Storage::disk('local')->path('face.ttf'), 'index' => 0];
        $faces[] = $face;
        $this->assertSame('confirmed', app(LetterTemplateTestEvidence::class)->state($version->fresh()));
        $faces[] = [...$face, 'family' => 'Missing Example Font', 'aliases' => ['Missing Example Font']];
        $this->assertSame('stale', app(LetterTemplateTestEvidence::class)->state($version->fresh()));
        $this->get(route('lt.test.download', [$version->template->uuid, $version->uuid, 'test_token' => $token]))->assertSessionHasErrors('test');
        $this->assertNotNull($version->fresh()->test_path);
    }

    public function test_rendered_font_usage_follows_actual_script_in_frozen_values(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'font-script-');
        $output = tempnam(sys_get_temp_dir(), 'font-render-');
        $word = new PhpWord();
        $word->addSection()->addText('${nama}', ['name' => 'Latin Font']);
        IOFactory::createWriter($word, 'Word2007')->save($source);
        $zip = new \ZipArchive();
        $zip->open($source);
        $xml = $zip->getFromName('word/document.xml');
        $xml = str_replace('w:cs="Latin Font"', 'w:cs="Arabic Font"', $xml);
        $zip->addFromString('word/document.xml', $xml);
        $zip->close();
        try {
            $usage = app(RenderLetterDocument::class)->render($source, ['schema_version' => 1, 'values' => ['nama' => 'العربية'], 'rows' => []], $output);
            $this->assertSame([['family' => 'Arabic Font', 'bold' => false, 'italic' => false]], $usage['requirements']);
        } finally {
            unlink($source);
            unlink($output);
        }
    }

    private function generate(LetterTemplateVersion $version): void
    {
        $this->post(route('lt.test.store', [$version->template->uuid, $version->uuid]), ['validation_token' => app(LetterTemplateTestEvidence::class)->validationToken($version->fresh()), 'values' => [], 'signers' => []])->assertSessionHasNoErrors();
    }

    private function source(LetterTemplateVersion $version): string
    {
        $path = tempnam(sys_get_temp_dir(), 'font-docx-');
        $escaping = Settings::isOutputEscapingEnabled();
        Settings::setOutputEscapingEnabled(true);
        try {
            $word = new PhpWord();
            $section = $word->addSection();
            $section->addText('${nomor_surat}', ['name' => 'Missing Example Font']);
            $section->addHeader()->addText('Header', ['name' => 'Missing Header Font', 'bold' => true]);
            IOFactory::createWriter($word, 'Word2007')->save($path);
            $bytes = file_get_contents($path);
            $this->post(route('lt.source.store', [$version->template->uuid, $version->uuid]), ['docx' => UploadedFile::fake()->createWithContent('source.docx', $bytes)])->assertSessionHasNoErrors();
            $this->post(route('lt.validation.store', [$version->template->uuid, $version->uuid]))->assertSessionHasNoErrors();

            return $bytes;
        } finally {
            Settings::setOutputEscapingEnabled($escaping);
            unlink($path);
        }
    }

    private function xml(string $xml): DOMDocument
    {
        $document = new DOMDocument();
        $document->loadXML($xml, LIBXML_NONET);

        return $document;
    }
}
