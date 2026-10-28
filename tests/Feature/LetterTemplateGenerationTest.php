<?php

namespace Tests\Feature;

use App\Enums\AsnStatusEnum;
use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\Employee;
use App\Models\MasterData\LetterTemplateSignerSlot;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVariableColumn;
use App\Models\MasterData\LetterTemplateVersion;
use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use App\Services\LetterTemplate\InspectLetterTemplateDocx;
use App\Services\LetterTemplate\LetterTemplateTestEvidence;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use DOMDocument;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class LetterTemplateGenerationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_generates_scalar_values_once_with_literal_fallback_dates_and_split_headers_footers(): void
    {
        $version = $this->draft();
        $name = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'nama']);
        $long = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'keterangan', 'type' => 2]);
        $date = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'tanggal', 'type' => 3]);
        $optional = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'opsional', 'is_required' => false, 'default_text' => '-']);
        $this->source($version, '${nama} ${keterangan} ${tanggal} ${opsional}', headerFooter: true, split: true);
        $source = Storage::disk('local')->get($version->fresh()->source_path);
        $payload = $this->payload($version, [$name->uuid => '0', $long->uuid => "Isi & <b> teks\n\${nama}", $date->uuid => '2026-10-25']);
        $this->post($this->url($version), $payload)->assertSessionHasNoErrors()->assertRedirectToRoute('lt.edit', $version->template->uuid);
        $version->refresh();
        $parts = $this->parts($version);
        foreach (['word/document.xml', 'word/header1.xml', 'word/footer1.xml'] as $part) {
            $this->assertStringContainsString('0 Isi & <b>', $parts[$part]->textContent);
            $this->assertStringContainsString('${nama}', $parts[$part]->textContent);
            $this->assertStringContainsString('25-10-2026 -', $parts[$part]->textContent);
            $this->assertStringContainsString('<w:br', $parts[$part]->saveXML());
            $this->assertStringNotContainsString('<b>', $parts[$part]->saveXML());
        }
        $this->assertSame($source, Storage::disk('local')->get($version->source_path));
        $this->assertSame($version->validated_source_sha256, $version->tested_source_sha256);
        $this->assertSame($version->validated_configuration_sha256, $version->tested_configuration_sha256);
        $this->assertSame(hash_file('sha256', Storage::disk('local')->path($version->test_path)), $version->test_sha256);
        $this->assertDatabaseHas('letter_template_audits', ['version_id' => $version->id, 'operation' => 'test.generated']);
    }

    public function test_employee_family_and_signer_attribute_fallback_use_one_selected_person_and_reference_content(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-25 12:00:00'));
        $version = $this->draft();
        $employee = Employee::factory()->create(['name' => 'Budi', 'front_title' => 'dr.', 'back_title' => null, 'rank' => 'Penata', 'grade' => 'III/c', 'nip' => null, 'join_date' => '2021-10-22', 'status' => AsnStatusEnum::AKTIF]);
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'pegawai', 'type' => 4, 'option_source' => 2, 'is_multiple' => false]);
        $variable->employeeAttributes()->create(['source_attribute' => 'nip', 'default_text' => 'NIP kosong']);
        $variable->employeeAttributes()->create(['source_attribute' => 'join_date', 'date_format' => 'd F Y']);
        $group = ReferenceGroup::factory()->create();
        $reference = ReferenceItem::factory()->for($group, 'group')->create(['content' => 'Isi referensi & teks']);
        $choice = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'referensi', 'type' => 4, 'option_source' => 1, 'reference_group_id' => $group->id]);
        $slot = LetterTemplateSignerSlot::factory()->for($version, 'version')->create();
        $slot->candidates()->create(['employee_id' => $employee->id]);
        $slot->employeeAttributes()->create(['source_attribute' => 'nip', 'default_text' => 'NIP penandatangan']);
        $this->source($version, '${pegawai} ${pegawai_pangkat} ${pegawai_golongan} ${pegawai_nip} ${pegawai_masa_kerja} ${pegawai_tanggal_bergabung} ${referensi} ${penandatangan_nip}');
        $this->post($this->url($version), $this->payload($version, [$variable->uuid => $employee->uuid, $choice->uuid => $reference->uuid], [$slot->uuid => $employee->uuid]))->assertSessionHasNoErrors();
        $text = $this->text($version);
        $this->assertStringContainsString('dr. Budi Penata III/c NIP kosong 5 tahun 3 hari 22 Oktober 2021 Isi referensi & teks NIP penandatangan', $text);
    }

    public function test_multiple_employee_and_reference_rows_follow_selection_order_and_column_date_formats(): void
    {
        $version = $this->draft();
        $a = Employee::factory()->create(['name' => 'A', 'front_title' => null, 'back_title' => null, 'status' => AsnStatusEnum::AKTIF, 'join_date' => '2020-10-25']);
        $b = Employee::factory()->create(['name' => 'B', 'front_title' => null, 'back_title' => null, 'status' => AsnStatusEnum::AKTIF, 'join_date' => '2021-10-25']);
        $employees = $this->multiple($version, 'pegawai', 2, [['row_number', 'nomor'], ['name', 'nama'], ['join_date', 'angka'], ['join_date', 'bulan', 'd F Y']]);
        $group = ReferenceGroup::factory()->create();
        $ra = ReferenceItem::factory()->for($group, 'group')->create(['content' => 'Referensi A', 'sort_order' => 0]);
        $rb = ReferenceItem::factory()->for($group, 'group')->create(['content' => 'Referensi B', 'sort_order' => 1]);
        $references = $this->multiple($version, 'referensi', 1, [['content', 'isi']], $group->id);
        $this->source($version, 'Judul', [['HEADER'], ['${nomor}', '${nama}', '${angka}', '${bulan}'], ['${isi}'], ['STATIS']]);
        $this->post($this->url($version), $this->payload($version, [$employees->uuid => [$b->uuid, $a->uuid], $references->uuid => [$rb->uuid, $ra->uuid]]))->assertSessionHasNoErrors();
        $text = $this->text($version);
        $this->assertStringContainsString('1B25-10-202125 Oktober 2021', $text);
        $this->assertStringContainsString('2A25-10-202025 Oktober 2020', $text);
        $this->assertLessThan(strpos($text, '2A'), strpos($text, '1B'));
        $this->assertLessThan(strpos($text, 'Referensi A'), strpos($text, 'Referensi B'));
        $this->assertStringContainsString('HEADER', $text);
        $this->assertStringContainsString('STATIS', $text);
    }

    #[DataProvider('emptyTables')]
    public function test_empty_optional_multiple_removes_only_template_rows_and_empty_tables(array $rows, int $tables): void
    {
        $version = $this->draft();
        $variable = $this->multiple($version, 'pegawai', 2, [['name', 'nama']]);
        $variable->update(['is_required' => false]);
        $this->source($version, 'Judul', $rows);
        $this->post($this->url($version), $this->payload($version))->assertSessionHasNoErrors();
        $document = $this->parts($version)['word/document.xml'];
        $this->assertSame($tables, $document->getElementsByTagNameNS(InspectLetterTemplateDocx::WORD_NAMESPACE, 'tbl')->length);
        $this->assertStringNotContainsString('${nama}', $document->textContent);
        if ($tables === 1) {
            $this->assertStringContainsString('HEADER', $document->textContent);
            $this->assertStringContainsString('STATIS', $document->textContent);
        }
    }

    public static function emptyTables(): array
    {
        return ['keep static rows' => [[['HEADER'], ['${nama}'], ['STATIS']], 1], 'remove empty table' => [[['${nama}']], 0]];
    }

    public function test_unused_variables_and_slots_do_not_require_values_and_optional_employee_uses_fallback(): void
    {
        $version = $this->draft();
        LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'unused', 'is_required' => true]);
        LetterTemplateSignerSlot::factory()->for($version, 'version')->create();
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'pegawai', 'type' => 4, 'option_source' => 2, 'is_required' => false]);
        $variable->employeeAttributes()->create(['source_attribute' => 'rank', 'default_text' => 'Pangkat belum diisi']);
        $this->source($version, '${pegawai_pangkat}');
        $this->post($this->url($version), $this->payload($version))->assertSessionHasNoErrors();
        $this->assertStringContainsString('Pangkat belum diisi', $this->text($version));
        $this->withoutVite()->get(route('lt.edit', $version->template->uuid))->assertInertia(fn(Assert $page) => $page
            ->has('test.data.variables', 1)->where('test.data.variables.0.id', $variable->uuid)->where('test.data.signer_slots', [])
            ->where('test.data.state', 'ready')->missing('test.data.test_path')->missing('test.data.test_disk'));
    }

    public function test_confirm_requires_current_downloadable_result_and_explicit_visual_statement(): void
    {
        $version = $this->draft();
        $this->source($version, 'Dokumen tanpa isian');
        $this->post($this->url($version, 'confirm'), ['test_token' => str_repeat('a', 64), 'confirmed' => true])->assertInvalid(['test']);
        $this->post($this->url($version), $this->payload($version))->assertSessionHasNoErrors();
        $version->refresh();
        $token = app(LetterTemplateTestEvidence::class)->token($version);
        $this->get($this->url($version, 'download') . '?test_token=' . $token)->assertOk()->assertDownload('hasil-uji-templat-v1.docx');
        $this->post($this->url($version, 'confirm'), ['test_token' => $token, 'confirmed' => false])->assertInvalid(['confirmed']);
        $this->post($this->url($version, 'confirm'), ['test_token' => $token, 'confirmed' => true])->assertSessionHasNoErrors();
        $version->refresh();
        $this->assertNotNull($version->visually_confirmed_at);
        $this->assertSame(auth()->user()->uuid, $version->visually_confirmed_by_uuid);
        $this->assertSame(auth()->user()->name, $version->visually_confirmed_by_name);
        $this->assertDatabaseHas('letter_template_audits', ['operation' => 'test.confirmed', 'version_id' => $version->id]);
        $this->post($this->url($version, 'confirm'), ['test_token' => $token, 'confirmed' => true])->assertSessionHasNoErrors();
        $this->assertSame(1, $version->audits()->where('operation', 'test.confirmed')->count());
    }

    public function test_regeneration_resets_confirmation_and_rejects_old_tokens_even_with_identical_output(): void
    {
        $this->freezeTime();
        $version = $this->draft();
        $this->source($version, 'Hasil yang sama');
        $this->post($this->url($version), $this->payload($version))->assertSessionHasNoErrors();
        $version->refresh();
        $oldPath = $version->test_path;
        $oldToken = app(LetterTemplateTestEvidence::class)->token($version);
        $this->post($this->url($version, 'confirm'), ['test_token' => $oldToken, 'confirmed' => true])->assertSessionHasNoErrors();
        $this->post($this->url($version), $this->payload($version))->assertSessionHasNoErrors();
        $version->refresh();
        $this->assertNull($version->visually_confirmed_at);
        $this->assertNotSame($oldToken, app(LetterTemplateTestEvidence::class)->token($version));
        Storage::disk('local')->assertMissing($oldPath);
        $this->post($this->url($version, 'confirm'), ['test_token' => $oldToken, 'confirmed' => true])->assertInvalid(['test']);
        $this->getJson($this->url($version, 'download') . '?test_token=' . $oldToken)->assertUnprocessable();
    }

    #[DataProvider('unusableResults')]
    public function test_stale_expired_missing_or_modified_files_cannot_be_confirmed_or_downloaded(string $kind): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-25 12:00:00'));
        $version = $this->draft();
        $this->source($version, 'Hasil');
        $this->post($this->url($version), $this->payload($version))->assertSessionHasNoErrors();
        $version->refresh();
        $token = app(LetterTemplateTestEvidence::class)->token($version);
        match ($kind) {
            'expired' => $version->update(['test_expires_at' => now()->subSecond()]),
            'expiry boundary' => $version->update(['test_expires_at' => now()]),
            'source missing' => Storage::disk('local')->delete($version->source_path),
            'source changed' => Storage::disk('local')->put($version->source_path, 'berubah'),
            'result missing' => Storage::disk('local')->delete($version->test_path),
            'result changed' => Storage::disk('local')->put($version->test_path, 'berubah'),
            'configuration' => $version->template->update(['name' => 'Nama berubah']),
            'locale' => config(['app.locale' => 'en']),
        };
        $this->post($this->url($version, 'confirm'), ['test_token' => $token, 'confirmed' => true])->assertInvalid(['test']);
        $this->getJson($this->url($version, 'download') . '?test_token=' . $token)->assertUnprocessable();
        $this->assertNull($version->fresh()->visually_confirmed_at);
    }

    public static function unusableResults(): array
    {
        return array_map(fn(string $kind): array => [$kind], ['expired', 'expiry boundary', 'source missing', 'source changed', 'result missing', 'result changed', 'configuration', 'locale']);
    }

    public function test_master_name_changes_preserve_result_but_inactive_selected_signer_blocks_evidence(): void
    {
        $version = $this->draft();
        $employee = Employee::factory()->create(['name' => 'Nama awal', 'status' => AsnStatusEnum::AKTIF]);
        $slot = LetterTemplateSignerSlot::factory()->for($version, 'version')->create();
        $slot->candidates()->create(['employee_id' => $employee->id]);
        $this->source($version, '${penandatangan_nama}');
        $this->post($this->url($version), $this->payload($version, [], [$slot->uuid => $employee->uuid]))->assertSessionHasNoErrors();
        $version->refresh();
        $token = app(LetterTemplateTestEvidence::class)->token($version);
        $employee->update(['name' => 'Nama baru']);
        $this->get($this->url($version, 'download') . '?test_token=' . $token)->assertOk();
        $this->assertStringContainsString('Nama awal', $this->text($version));
        $this->assertStringNotContainsString('Nama baru', $this->text($version));
        $employee->update(['status' => AsnStatusEnum::PENSIUN]);
        $this->post($this->url($version, 'confirm'), ['test_token' => $token, 'confirmed' => true])->assertInvalid(['test']);
        $this->getJson($this->url($version, 'download') . '?test_token=' . $token)->assertUnprocessable();
    }

    public function test_signers_must_be_distinct_active_slot_candidates_even_after_form_was_loaded(): void
    {
        $version = $this->draft();
        $employee = Employee::factory()->create(['status' => AsnStatusEnum::AKTIF]);
        $other = Employee::factory()->create(['status' => AsnStatusEnum::AKTIF]);
        $first = LetterTemplateSignerSlot::factory()->for($version, 'version')->create(['sort_order' => 1]);
        $second = LetterTemplateSignerSlot::factory()->for($version, 'version')->create(['sort_order' => 2]);
        $first->candidates()->create(['employee_id' => $employee->id]);
        $second->candidates()->create(['employee_id' => $employee->id]);
        $this->source($version, '${penandatangan_1_nip} ${penandatangan_2_pangkat}');
        $this->post($this->url($version), $this->payload($version, [], [$first->uuid => $employee->uuid, $second->uuid => $employee->uuid]))->assertInvalid(['signers']);
        $this->post($this->url($version), $this->payload($version, [], [$first->uuid => $employee->uuid, $second->uuid => $other->uuid]))->assertInvalid(['signers.' . $second->uuid]);
        $second->candidates()->create(['employee_id' => $other->id]);
        $this->post(route('lt.validation.store', [$version->template->uuid, $version->uuid]))->assertSessionHasNoErrors();
        $other->update(['status' => AsnStatusEnum::PENSIUN]);
        $this->post($this->url($version), $this->payload($version, [], [$first->uuid => $employee->uuid, $second->uuid => $other->uuid]))->assertInvalid(['signers.' . $second->uuid]);
        $this->assertNull($version->fresh()->test_path);
        $other->update(['status' => AsnStatusEnum::AKTIF]);
        $this->post($this->url($version), $this->payload($version, [], [$first->uuid => $employee->uuid, $second->uuid => $other->uuid]))->assertSessionHasNoErrors();
        $this->assertNotNull($version->fresh()->test_path);
    }

    #[DataProvider('invalidValues')]
    public function test_required_and_type_limits_are_enforced_without_using_fallback_as_input(int $type, bool $required, mixed $value): void
    {
        $version = $this->draft();
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'isian', 'type' => $type, 'is_required' => $required, 'default_text' => 'Fallback']);
        $this->source($version, '${isian}');
        $this->post($this->url($version), $this->payload($version, [$variable->uuid => $value]))->assertInvalid(['values.' . $variable->uuid]);
        $this->assertNull($version->fresh()->test_path);
    }

    public static function invalidValues(): array
    {
        return ['required' => [1, true, null], 'date required' => [3, true, null], 'short limit' => [1, false, str_repeat('a', 256)], 'long limit' => [2, false, str_repeat('a', 10001)], 'invalid date' => [3, false, '2026-02-30'], 'wrong type' => [1, false, ['text']], 'control character' => [2, false, "abc\x01"]];
    }

    public function test_employee_and_reference_choices_must_be_active_and_belong_to_the_configured_group(): void
    {
        $version = $this->draft();
        $employee = Employee::factory()->create(['status' => AsnStatusEnum::PENSIUN]);
        $employeeVariable = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'pegawai', 'type' => 4, 'option_source' => 2]);
        $group = ReferenceGroup::factory()->create();
        $foreign = ReferenceItem::factory()->create();
        $referenceVariable = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'referensi', 'type' => 4, 'option_source' => 1, 'reference_group_id' => $group->id]);
        $this->source($version, '${pegawai} ${referensi}');
        $this->post($this->url($version), $this->payload($version, [$employeeVariable->uuid => $employee->uuid, $referenceVariable->uuid => $foreign->uuid]))->assertInvalid(['values.' . $employeeVariable->uuid]);
        $employee->update(['status' => AsnStatusEnum::AKTIF]);
        $this->post($this->url($version), $this->payload($version, [$employeeVariable->uuid => $employee->uuid, $referenceVariable->uuid => $foreign->uuid]))->assertInvalid(['values.' . $referenceVariable->uuid]);
        $this->assertNull($version->fresh()->test_path);
    }

    public function test_unknown_fields_duplicate_multiple_choices_and_row_limits_are_rejected(): void
    {
        $version = $this->draft();
        $variable = $this->multiple($version, 'pegawai', 2, [['name', 'nama']]);
        $this->source($version, 'Tabel', [['${nama}']]);
        $this->post($this->url($version), $this->payload($version))->assertInvalid(['values.' . $variable->uuid]);
        $employee = Employee::factory()->create(['status' => AsnStatusEnum::AKTIF]);
        $this->post($this->url($version), $this->payload($version, ['asing' => 'nilai']))->assertInvalid(['values']);
        $this->post($this->url($version), $this->payload($version, [$variable->uuid => [$employee->uuid, $employee->uuid]]))->assertInvalid(['values.' . $variable->uuid . '.0']);
        config(['letter-templates.test.max_rows' => 0]);
        $this->post($this->url($version), $this->payload($version, [$variable->uuid => [$employee->uuid]]))->assertInvalid(['values.' . $variable->uuid]);
    }

    public function test_failed_generation_rolls_back_proof_and_cleans_only_new_preview_file(): void
    {
        $version = $this->draft();
        $this->source($version, 'Hasil');
        $this->post($this->url($version), $this->payload($version))->assertSessionHasNoErrors();
        $version->refresh();
        $oldPath = $version->test_path;
        $token = app(LetterTemplateTestEvidence::class)->token($version);
        $this->post($this->url($version, 'confirm'), ['test_token' => $token, 'confirmed' => true])->assertSessionHasNoErrors();
        Exceptions::fake();
        LetterTemplateVersion::updating(function (LetterTemplateVersion $model): void {
            if ($model->isDirty('test_path')) {
                throw new RuntimeException('Simulasi gagal menyimpan hasil uji.');
            }
        });
        try {
            $this->post($this->url($version), $this->payload($version))->assertInternalServerError();
        } finally {
            LetterTemplateVersion::getEventDispatcher()->forget('eloquent.updating: ' . LetterTemplateVersion::class);
        }
        Exceptions::assertReported(RuntimeException::class);
        $this->assertSame($oldPath, $version->fresh()->test_path);
        $this->assertNotNull($version->fresh()->visually_confirmed_at);
        $this->assertCount(1, Storage::disk('local')->allFiles(config('letter-templates.paths.previews')));
        $this->assertSame(1, $version->audits()->where('operation', 'test.generated')->count());
    }

    public function test_deadline_and_output_size_failures_do_not_replace_previous_evidence(): void
    {
        $version = $this->draft();
        $this->source($version, 'Hasil');
        config(['letter-templates.test.max_output_bytes' => 1]);
        $this->post($this->url($version), $this->payload($version))->assertInvalid(['test']);
        $this->assertNull($version->fresh()->test_path);
        $this->assertSame([], Storage::disk('local')->allFiles(config('letter-templates.paths.previews')));
        config(['letter-templates.test.max_output_bytes' => 200000, 'letter-templates.test.timeout_seconds' => -1]);
        $this->post($this->url($version), $this->payload($version))->assertInvalid(['test']);
        $this->assertNull($version->fresh()->test_path);
    }

    public function test_generate_requires_current_validation_and_rejects_a_form_from_an_older_configuration(): void
    {
        $version = $this->draft();
        $this->post($this->url($version), $this->payload($version))->assertInvalid(['test']);
        $this->source($version, 'Hasil');
        $old = $this->payload($version);
        $version->template->update(['name' => 'Nama baru']);
        $this->post($this->url($version), $old)->assertInvalid(['test']);
        $this->post(route('lt.validation.store', [$version->template->uuid, $version->uuid]))->assertSessionHasNoErrors();
        $this->post($this->url($version), $old)->assertInvalid(['test']);
        $this->assertNull($version->fresh()->test_path);
    }

    #[DataProvider('immutableVersions')]
    public function test_published_archived_and_used_versions_cannot_generate_or_confirm(LetterTemplateVersionStatusEnum $status, bool $used): void
    {
        $version = $this->draft();
        $this->source($version, 'Hasil');
        $version->update(['status' => $status, 'first_used_at' => $used ? now() : null]);
        $this->post($this->url($version), $this->payload($version))->assertInvalid(['test']);
        $this->post($this->url($version, 'confirm'), ['test_token' => str_repeat('a', 64), 'confirmed' => true])->assertInvalid(['test']);
    }

    public static function immutableVersions(): array
    {
        return [[LetterTemplateVersionStatusEnum::PUBLISHED, false], [LetterTemplateVersionStatusEnum::ARCHIVED, false], [LetterTemplateVersionStatusEnum::DRAFT, true]];
    }

    public function test_endpoints_require_authentication_and_scope_versions_to_their_template(): void
    {
        $version = $this->draft();
        $other = LetterTemplateVersion::factory()->create();
        foreach (['store', 'download', 'confirm'] as $operation) {
            $url = route('lt.test.' . $operation, [$version->template->uuid, $other->uuid]);
            ($operation === 'download' ? $this->get($url) : $this->post($url))->assertNotFound();
        }
        auth()->logout();
        $this->post($this->url($version), [])->assertRedirectToRoute('login.index');
        $this->get($this->url($version, 'download'))->assertRedirectToRoute('login.index');
        $this->post($this->url($version, 'confirm'), [])->assertRedirectToRoute('login.index');
        $this->assertDatabaseCount('letter_template_audits', 0);
    }

    #[DataProvider('expansionModes')]
    public function test_xml_expansion_budget_is_checked_before_large_tables_or_literal_values_are_written(bool $rows): void
    {
        $version = $this->draft();
        if ($rows) {
            $variable = $this->multiple($version, 'pegawai', 2, [['name', 'isian']]);
            $employees = Employee::factory()->count(10)->create(['status' => AsnStatusEnum::AKTIF]);
            $this->source($version, 'Judul', [[str_repeat('Teks statis pada baris. ', 100) . '${isian}']]);
            $values = [$variable->uuid => $employees->pluck('uuid')->all()];
        } else {
            $variable = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'isian', 'type' => 2]);
            $this->source($version, '${isian}');
            $values = [$variable->uuid => str_repeat('Teks & isi. ', 600)];
        }
        $path = Storage::disk('local')->path($version->fresh()->source_path);
        $size = max(array_map(fn(DOMDocument $part): int => strlen($part->saveXML()), app(InspectLetterTemplateDocx::class)->read($path)));
        config(['letter-templates.zip.max_xml_bytes' => $size + 1024]);
        $this->post($this->url($version), $this->payload($version, $values))->assertInvalid(['test' => $rows ? 'Isi tabel hasil uji terlalu besar. Kurangi data pilihan.' : 'Isi hasil uji terlalu besar. Kurangi panjang isian atau data pilihan.']);
        $this->assertNull($version->fresh()->test_path);
    }

    public static function expansionModes(): array
    {
        return ['table rows' => [true], 'literal text' => [false]];
    }

    public function test_test_result_offers_private_pdf_preview_and_download_without_changing_docx_or_confirmation(): void
    {
        $version = $this->draft();
        $this->source($version, 'Surat contoh');
        $this->post($this->url($version), $this->payload($version, []))->assertSessionHasNoErrors();
        $version->refresh();
        $original = Storage::disk($version->test_disk)->get($version->test_path);
        $pdf = "%PDF-1.7\n1 0 obj <</Type /Catalog>> endobj\n%%EOF\n";
        Process::fake(['*--convert-to*' => function ($process) use ($pdf) {
            $output = $process->command[array_search('--outdir', $process->command, true) + 1];
            file_put_contents($output . '/document.pdf', $pdf);

            return Process::result();
        }]);
        $url = route('lt.test.download', [$version->template->uuid, $version->uuid, 'test_token' => app(LetterTemplateTestEvidence::class)->token($version), 'format' => 'pdf']);
        foreach ([false, true] as $download) {
            $response = $this->get($url . '&download=' . (int) $download)->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private');
            $path = $response->baseResponse->getFile()->getPathname();
            try {
                $this->assertSame($pdf, file_get_contents($path));
                if ($download) {
                    $response->assertDownload('hasil-uji-templat-v' . $version->version_number . '.pdf');
                }
            } finally {
                unlink($path);
            }
        }
        $this->assertSame($original, Storage::disk($version->test_disk)->get($version->test_path));
        $this->assertNull($version->fresh()->visually_confirmed_at);
        $this->assertSame([], Storage::disk(config('letters.disk'))->allFiles(config('letters.paths.staging')));
        $this->getJson($url . '&format=invalid')->assertUnprocessable()->assertJsonValidationErrors('format');
        $version->update(['test_expires_at' => now()->subSecond()]);
        $this->getJson($url)->assertUnprocessable()->assertJsonValidationErrors('test');
    }

    public function test_pdf_conversion_failure_keeps_docx_available_and_cleans_temporary_files(): void
    {
        $version = $this->draft();
        $this->source($version, 'Surat contoh');
        $this->post($this->url($version), $this->payload($version, []))->assertSessionHasNoErrors();
        $version->refresh();
        Exceptions::fake();
        Process::fake(['*--convert-to*' => Process::result(exitCode: 1)]);
        $args = [$version->template->uuid, $version->uuid, 'test_token' => app(LetterTemplateTestEvidence::class)->token($version)];
        $this->getJson(route('lt.test.download', [...$args, 'format' => 'pdf']))->assertUnprocessable()->assertJsonValidationErrors('pdf');
        $this->get(route('lt.test.download', $args))->assertOk()->assertDownload();
        $this->assertSame([], Storage::disk(config('letters.disk'))->allFiles(config('letters.paths.staging')));
        $this->assertNull($version->fresh()->visually_confirmed_at);
    }

    public function test_template_pdf_is_discarded_when_the_test_result_changes_during_conversion(): void
    {
        $version = $this->draft();
        $this->source($version, 'Surat contoh');
        $this->post($this->url($version), $this->payload($version, []))->assertSessionHasNoErrors();
        $version->refresh();
        $token = app(LetterTemplateTestEvidence::class)->token($version);
        Process::fake(['*--convert-to*' => function ($process) use ($version) {
            $output = $process->command[array_search('--outdir', $process->command, true) + 1];
            file_put_contents($output . '/document.pdf', "%PDF-1.7\n1 0 obj <</Type /Catalog>> endobj\n%%EOF\n");
            $version->update(['test_expires_at' => now()->subSecond()]);

            return Process::result();
        }]);

        $this->getJson(route('lt.test.download', [$version->template->uuid, $version->uuid, 'test_token' => $token, 'format' => 'pdf']))->assertUnprocessable()->assertJsonValidationErrors('test');

        $this->assertSame([], Storage::disk(config('letters.disk'))->allFiles(config('letters.paths.staging')));
        $this->assertNull($version->fresh()->visually_confirmed_at);
    }

    private function draft(): LetterTemplateVersion
    {
        Storage::fake('local');
        $this->actingAs(UserFactory::new()->create());

        return LetterTemplateVersion::factory()->create();
    }

    private function multiple(LetterTemplateVersion $version, string $name, int $source, array $columns, ?int $group = null): LetterTemplateVariable
    {
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => $name, 'type' => 4, 'option_source' => $source, 'reference_group_id' => $group, 'is_multiple' => true, 'is_required' => true]);
        foreach ($columns as $index => $column) {
            LetterTemplateVariableColumn::factory()->for($variable, 'variable')->create(['source_attribute' => $column[0], 'placeholder' => $column[1], 'date_format' => $column[2] ?? null, 'sort_order' => $index]);
        }

        return $variable;
    }

    private function source(LetterTemplateVersion $version, string $text, array $rows = [], bool $headerFooter = false, bool $split = false): void
    {
        $path = tempnam(sys_get_temp_dir(), 'letter-test-');
        $escaping = Settings::isOutputEscapingEnabled();
        Settings::setOutputEscapingEnabled(true);
        try {
            $word = new PhpWord();
            $section = $word->addSection();
            $section->addText($text);
            if ($headerFooter) {
                $section->addHeader()->addText($text);
                $section->addFooter()->addText($text);
            }
            if ($rows !== []) {
                $table = $section->addTable();
                foreach ($rows as $row) {
                    $table->addRow();
                    foreach ($row as $cell) {
                        $table->addCell(2000)->addText($cell);
                    }
                }
            }
            IOFactory::createWriter($word, 'Word2007')->save($path);
            if ($split) {
                $zip = new ZipArchive();
                $zip->open($path);
                $zip->addFromString('word/document.xml', str_replace('${nama}', '${na</w:t></w:r><w:r><w:t>ma}', $zip->getFromName('word/document.xml')));
                $zip->close();
            }
            $this->post(route('lt.source.store', [$version->template->uuid, $version->uuid]), ['docx' => UploadedFile::fake()->createWithContent('source.docx', file_get_contents($path))])->assertSessionHasNoErrors();
            $this->post(route('lt.validation.store', [$version->template->uuid, $version->uuid]))->assertSessionHasNoErrors();
            $version->refresh();
        } finally {
            Settings::setOutputEscapingEnabled($escaping);
            unlink($path);
        }
    }

    private function payload(LetterTemplateVersion $version, array $values = [], array $signers = []): array
    {
        return ['validation_token' => app(LetterTemplateTestEvidence::class)->validationToken($version->fresh()), 'values' => $values, 'signers' => $signers];
    }

    private function url(LetterTemplateVersion $version, string $operation = 'store'): string
    {
        return route('lt.test.' . $operation, [$version->template->uuid, $version->uuid]);
    }

    private function parts(LetterTemplateVersion $version): array
    {
        $version->refresh();

        return app(InspectLetterTemplateDocx::class)->read(Storage::disk('local')->path($version->test_path));
    }

    private function text(LetterTemplateVersion $version): string
    {
        return $this->parts($version)['word/document.xml']->textContent;
    }
}
