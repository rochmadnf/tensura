<?php

namespace Tests\Feature;

use App\Enums\AsnStatusEnum;
use App\Enums\LetterTemplateOptionSourceEnum;
use App\Enums\LetterTemplateVariableTypeEnum;
use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\Employee;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateSignerCandidate;
use App\Models\MasterData\LetterTemplateSignerSlot;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVariableColumn;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\LetterTemplate\EmployeeAttributeCatalog;
use App\Services\LetterTemplate\InspectLetterTemplateDocx;
use App\Services\LetterTemplate\LetterTemplateProcessor;
use App\Services\LetterTemplate\ValidateLetterTemplateDocx;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LetterTemplateExampleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_draft_without_configuration_provides_onboarding_guide_to_a_replacement_admin(): void
    {
        Storage::fake('local');
        $version = LetterTemplateVersion::factory()->create(['letter_template_id' => LetterTemplate::factory()->state(['name' => 'Surat ${judul} & Contoh'])]);
        $before = $version->fresh()->getAttributes();
        $this->actingAs(UserFactory::new()->create());
        $first = $this->example($version);
        $this->actingAs(UserFactory::new()->create());
        $second = $this->example($version);

        $this->assertSame([], $first['variables']);
        $this->assertSame($first, $second);
        $this->assertStringContainsString('Surat $ {judul} & Contoh', $first['text']);
        $this->assertStringContainsString('Draft ini belum memiliki variabel', $first['text']);
        $this->assertStringContainsString('Langkah Berikutnya', $first['text']);
        $this->assertSame($before, $version->fresh()->getAttributes());
        $this->assertDatabaseCount('letter_template_audits', 0);
        Storage::disk('local')->assertEmpty();
    }

    public function test_example_contains_exact_configured_placeholders_and_no_candidate_personal_data(): void
    {
        Storage::fake('local');
        $version = LetterTemplateVersion::factory()->create();
        LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'tujuan', 'label' => 'Tujuan ${bukan_placeholder} & Keterangan']);
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'pegawai', 'type' => LetterTemplateVariableTypeEnum::COMBOBOX, 'option_source' => LetterTemplateOptionSourceEnum::EMPLOYEE, 'is_multiple' => true]);
        LetterTemplateVariableColumn::factory()->for($variable, 'variable')->create(['source_attribute' => 'row_number', 'placeholder' => 'pegawai_no']);
        LetterTemplateVariableColumn::factory()->for($variable, 'variable')->create(['source_attribute' => 'name', 'placeholder' => 'pegawai_nama', 'sort_order' => 1]);
        $slot = LetterTemplateSignerSlot::factory()->for($version, 'version')->create();
        $employee = Employee::factory()->create(['name' => 'Nama Rahasia Kandidat', 'status' => AsnStatusEnum::AKTIF]);
        LetterTemplateSignerCandidate::factory()->for($slot, 'slot')->create(['employee_id' => $employee->id]);
        $this->actingAs(UserFactory::new()->create());

        $result = $this->example($version, true);

        $expected = ['pegawai_nama', 'pegawai_no', 'tujuan', ...array_keys(EmployeeAttributeCatalog::tokens('penandatangan'))];
        sort($expected);
        $this->assertSame($expected, $result['variables']);
        $this->assertContains('penandatangan_pangkat', $result['variables']);
        $this->assertContains('penandatangan_masa_kerja', $result['variables']);
        $this->assertStringContainsString('Tujuan $ {bukan_placeholder} & Keterangan', $result['text']);
        $this->assertStringNotContainsString($employee->name, $result['text']);
        $this->assertStringNotContainsString($employee->nik, $result['text']);
        $this->assertTrue($result['valid']);
        $this->assertNull($version->fresh()->validated_at);
        $this->assertNull($version->fresh()->source_path);
    }

    public function test_examples_follow_configuration_changes_and_keep_historical_version_isolated(): void
    {
        Storage::fake('local');
        $version = LetterTemplateVersion::factory()->create(['version_number' => 2]);
        $history = LetterTemplateVersion::factory()->for($version->template, 'template')->create(['version_number' => 1, 'status' => LetterTemplateVersionStatusEnum::PUBLISHED, 'source_disk' => 'local', 'source_path' => 'history.docx', 'validated_at' => now(), 'tested_at' => now(), 'visually_confirmed_at' => now()]);
        Storage::disk('local')->put('history.docx', 'historical source');
        $old = LetterTemplateVariable::factory()->for($history, 'version')->create(['name' => 'lama']);
        $current = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'baru']);
        $this->actingAs(UserFactory::new()->create());
        $this->assertSame(['baru'], $this->example($version)['variables']);
        $current->update(['name' => 'terbaru']);
        $this->assertSame(['terbaru'], $this->example($version)['variables']);
        $before = $history->fresh()->getAttributes();

        $this->assertSame(['lama'], $this->example($history)['variables']);

        $this->assertSame($before, $history->fresh()->getAttributes());
        $this->assertModelExists($old);
        $this->assertSame('historical source', Storage::disk('local')->get('history.docx'));
    }

    public function test_scalar_employee_guide_contains_all_tokens_and_owner_settings_without_extra_placeholders(): void
    {
        Storage::fake('local');
        $version = LetterTemplateVersion::factory()->create();
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'pegawai', 'type' => LetterTemplateVariableTypeEnum::COMBOBOX, 'option_source' => LetterTemplateOptionSourceEnum::EMPLOYEE, 'is_multiple' => false]);
        $variable->employeeAttributes()->create(['source_attribute' => 'nip', 'default_text' => 'NIP ${bukan_token}']);
        $variable->employeeAttributes()->create(['source_attribute' => 'join_date', 'date_format' => 'd F Y']);
        $this->actingAs(UserFactory::new()->create());
        $result = $this->example($version, true);

        $this->assertCount(26, $result['variables']);
        $this->assertContains('pegawai_pangkat', $result['variables']);
        $this->assertContains('pegawai_golongan', $result['variables']);
        $this->assertContains('pegawai_masa_kerja_hari', $result['variables']);
        $this->assertContains('pegawai_tanggal_terbit_sip', $result['variables']);
        $this->assertNotContains('bukan_token', $result['variables']);
        $this->assertStringContainsString('NIP $ {bukan_token}', $result['text']);
        $this->assertStringContainsString('format d F Y', $result['text']);
        $this->assertTrue($result['valid']);
    }

    private function example(LetterTemplateVersion $version, bool $validate = false): array
    {
        $response = $this->get(route('lt.example.download', [$version->template->uuid, $version->uuid]))
            ->assertOk()->assertDownload('contoh-templat-v' . $version->version_number . '.docx');
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            $parts = app(InspectLetterTemplateDocx::class)->read($path);
            $processor = new LetterTemplateProcessor($path);
            $variables = $processor->getVariables();
            unset($processor);
            sort($variables);
            $result = ['text' => $parts['word/document.xml']->textContent, 'variables' => $variables];
            if ($validate) {
                $result['valid'] = app(ValidateLetterTemplateDocx::class)->inspectPlaceholders($path, $version)['valid'];
            }

            return $result;
        } finally {
            unlink($path);
        }
    }
}
