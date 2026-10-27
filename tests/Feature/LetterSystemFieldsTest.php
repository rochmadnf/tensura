<?php

namespace Tests\Feature;

use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\Letter\LetterSystemFields;
use App\Services\Letter\RenderLetterDocument;
use App\Services\Letter\ResolveLetterDocumentSnapshot;
use App\Services\LetterTemplate\BuildLetterTemplateExample;
use App\Services\LetterTemplate\CopyLetterTemplateVersion;
use App\Services\LetterTemplate\InspectLetterTemplateDocx;
use App\Services\LetterTemplate\LetterTemplateTestEvidence;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use Tests\TestCase;

class LetterSystemFieldsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_system_tokens_generate_in_body_header_footer_without_consuming_numbers(): void
    {
        Storage::fake('local');
        $this->actingAs(UserFactory::new()->create());
        $this->travelTo(CarbonImmutable::parse('2026-10-26 12:00:00', config('app.timezone')));
        $version = LetterTemplateVersion::factory()->create(['letter_date_format' => 'd F Y']);
        $number = $version->letterNumber->only(['page_number', 'sequence_number']);
        $source = $this->source('${nomor_surat} ${tanggal_surat}');
        try {
            $this->post(route('lt.source.store', [$version->template->uuid, $version->uuid]), ['docx' => UploadedFile::fake()->createWithContent('source.docx', file_get_contents($source))])->assertSessionHasNoErrors();
            $this->post(route('lt.validation.store', [$version->template->uuid, $version->uuid]))->assertSessionHasNoErrors();
            $version->refresh();
            $this->assertTrue($version->validation_result['valid']);
            $this->post(route('lt.test.store', [$version->template->uuid, $version->uuid]), ['validation_token' => app(LetterTemplateTestEvidence::class)->validationToken($version), 'values' => [], 'signers' => []])->assertSessionHasNoErrors();
            $version->refresh();
            foreach (['word/document.xml', 'word/header1.xml', 'word/footer1.xml'] as $part) {
                $text = app(InspectLetterTemplateDocx::class)->read(Storage::disk('local')->path($version->test_path))[$part]->textContent;
                $this->assertStringContainsString('/XX.XX/PKM-KMJ/X/2026', $text);
                $this->assertStringContainsString('26 Oktober 2026', $text);
            }
            $this->assertSame($number, $version->letterNumber->fresh()->only(['page_number', 'sequence_number']));
            $this->assertDatabaseCount('letter_number_periods', 0);
        } finally {
            unlink($source);
        }
    }

    public function test_date_format_is_versioned_audited_and_invalidates_evidence(): void
    {
        $version = LetterTemplateVersion::factory()->create(['validated_at' => now(), 'visually_confirmed_at' => now()]);
        $this->actingAs(UserFactory::new()->create())->patch($this->url($version), ['letter_date_format' => 'd F Y'])->assertSessionHasNoErrors();
        $version->refresh();
        $this->assertSame('d F Y', $version->letter_date_format);
        $this->assertNull($version->validated_at);
        $this->assertNull($version->visually_confirmed_at);
        $this->assertDatabaseHas('letter_template_audits', ['version_id' => $version->id, 'operation' => 'configuration.updated']);
        $files = [];
        $copy = app(CopyLetterTemplateVersion::class)->copy($version->template, $version, $files);
        $this->assertSame('d F Y', $copy->letter_date_format);
        $path = app(BuildLetterTemplateExample::class)->build($version);
        try {
            $text = app(InspectLetterTemplateDocx::class)->read($path)['word/document.xml']->textContent;
            $this->assertStringContainsString('${nomor_surat}', $text);
            $this->assertStringContainsString('${tanggal_surat}', $text);
            $this->assertStringContainsString('d F Y', $text);
        } finally {
            unlink($path);
        }
    }

    public function test_date_setting_requires_auth_valid_pattern_draft_and_correct_parent(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $this->patch($this->url($version), ['letter_date_format' => 'd F Y'])->assertRedirectToRoute('login.index');
        $this->actingAs(UserFactory::new()->create());
        foreach (['', str_repeat('d', 101), "d\nY"] as $value) {
            $this->patch($this->url($version), ['letter_date_format' => $value])->assertSessionHasErrors('letter_date_format');
        }
        $other = LetterTemplateVersion::factory()->create();
        $this->patch(route('lt.system-fields.update', [$other->template->uuid, $version->uuid]), ['letter_date_format' => 'Y'])->assertNotFound();
        $version->update(['status' => LetterTemplateVersionStatusEnum::PUBLISHED]);
        $this->patch($this->url($version), ['letter_date_format' => 'Y'])->assertSessionHasErrors('test');
        $this->assertSame('d-m-Y', $version->fresh()->letter_date_format);
    }

    public function test_system_names_are_reserved_for_variables_and_columns(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $this->actingAs(UserFactory::new()->create());
        foreach (LetterSystemFields::NAMES as $name) {
            $variable = ['name' => $name, 'label' => 'Bentrok', 'type' => 1, 'is_required' => false, 'option_source' => null, 'reference_group' => null, 'is_multiple' => false, 'columns' => []];
            $this->put(route('lt.configuration.update', [$version->template->uuid, $version->uuid]), ['variables' => [$variable], 'signer_slots' => []])->assertSessionHasErrors('variables.0.name');
            $variable = [...$variable, 'name' => 'pegawai', 'type' => 4, 'option_source' => 2, 'is_multiple' => true, 'columns' => [['source_attribute' => 'name', 'placeholder' => $name]]];
            $this->put(route('lt.configuration.update', [$version->template->uuid, $version->uuid]), ['variables' => [$variable], 'signer_slots' => []])->assertSessionHasErrors('variables.0.columns.0.placeholder');
        }
        $this->assertDatabaseCount('letter_template_variables', 0);
    }

    public function test_rendering_uses_frozen_snapshot_after_configuration_changes_and_keeps_literal_tokens(): void
    {
        $version = LetterTemplateVersion::factory()->create(['validation_result' => ['placeholders' => ['nama', 'nomor_surat', 'tanggal_surat']]]);
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'nama', 'default_text' => 'Nilai lama ${tanggal_surat}']);
        $snapshot = app(ResolveLetterDocumentSnapshot::class)->resolve($version, ['values' => [], 'signers' => []], CarbonImmutable::parse('2026-10-26'), '445/01.01/PKM-KMJ/X/2026');
        $variable->update(['default_text' => 'Nilai baru']);
        $version->update(['letter_date_format' => 'Y']);
        $source = $this->source('${nama} ${nomor_surat} ${tanggal_surat}');
        $target = tempnam(sys_get_temp_dir(), 'letter-snapshot-');
        try {
            app(RenderLetterDocument::class)->render($source, $snapshot, $target);
            $text = app(InspectLetterTemplateDocx::class)->read($target)['word/document.xml']->textContent;
            $this->assertStringContainsString('Nilai lama ${tanggal_surat}', $text);
            $this->assertStringNotContainsString('Nilai baru', $text);
            $this->assertStringContainsString('445/01.01/PKM-KMJ/X/2026 26-10-2026', $text);
        } finally {
            unlink($source);
            unlink($target);
        }
    }

    private function url(LetterTemplateVersion $version): string
    {
        return route('lt.system-fields.update', [$version->template->uuid, $version->uuid]);
    }

    private function source(string $text): string
    {
        $path = tempnam(sys_get_temp_dir(), 'letter-system-');
        $escaping = Settings::isOutputEscapingEnabled();
        Settings::setOutputEscapingEnabled(true);
        try {
            $word = new PhpWord();
            $section = $word->addSection();
            $section->addText($text);
            $section->addHeader()->addText($text);
            $section->addFooter()->addText($text);
            IOFactory::createWriter($word, 'Word2007')->save($path);
        } finally {
            Settings::setOutputEscapingEnabled($escaping);
        }

        return $path;
    }
}
