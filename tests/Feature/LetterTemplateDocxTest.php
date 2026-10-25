<?php

namespace Tests\Feature;

use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\LetterTemplateSignerSlot;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVariableColumn;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\LetterTemplate\InspectLetterTemplateDocx;
use App\Services\LetterTemplate\LetterTemplateProcessor;
use Database\Factories\UserFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class LetterTemplateDocxTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_upload_stores_private_generated_path_and_metadata_without_accepting_system_fields(): void
    {
        Storage::fake('local');
        $version = LetterTemplateVersion::factory()->create(['validated_at' => now(), 'tested_at' => now(), 'visually_confirmed_at' => now()]);
        $file = $this->docx();
        $content = file_get_contents($file->getPathname());

        $this->actingAs(UserFactory::new()->create())->post($this->route($version, 'source.store'), ['docx' => $file, 'source_disk' => 'public', 'source_path' => 'hacked.docx', 'status' => 2])
            ->assertSessionHasNoErrors()->assertRedirectToRoute('lt.edit', $version->template->uuid);

        $stored = $version->fresh();
        $this->assertSame('local', $stored->source_disk);
        $this->assertStringStartsWith('letter-templates/sources/' . $version->uuid . '/', $stored->source_path);
        $this->assertSame('source.docx', $stored->source_original_name);
        $this->assertSame(strlen($content), $stored->source_size);
        $this->assertSame(hash('sha256', $content), $stored->source_sha256);
        $this->assertSame(LetterTemplateVersionStatusEnum::DRAFT, $stored->status);
        $this->assertNull($stored->validated_at);
        $this->assertNull($stored->tested_at);
        $this->assertNull($stored->visually_confirmed_at);
        Storage::disk('local')->assertExists($stored->source_path);
        $this->assertDatabaseHas('letter_template_audits', ['version_id' => $version->id, 'operation' => 'source.uploaded']);
    }

    public function test_validates_split_runs_headers_and_footers_and_binds_proof_to_file_and_configuration(): void
    {
        Storage::fake('local');
        $version = LetterTemplateVersion::factory()->create();
        LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'nama']);
        $file = $this->docx('${nama}', [], true, true);
        $this->actingAs(UserFactory::new()->create())->withoutVite()->post($this->route($version, 'source.store'), ['docx' => $file])->assertSessionHasNoErrors();

        $this->post($this->route($version, 'validation.store'))->assertSessionHasNoErrors();

        $stored = $version->fresh();
        $this->assertTrue($stored->validation_result['valid']);
        $this->assertSame(['nama'], $stored->validation_result['placeholders']);
        $this->assertSame($stored->source_sha256, $stored->validated_source_sha256);
        $this->assertNotNull($stored->validated_configuration_sha256);
        $this->assertNotNull($stored->validated_at);
        $this->get(route('lt.edit', $version->template->uuid))->assertInertia(fn(Assert $page) => $page
            ->where('document.data.state', 'valid')->where('document.data.source.name', 'source.docx')
            ->missing('document.data.source_path')->missing('document.data.source_disk'));
        $stored->variables->sole()->update(['label' => 'Label berubah']);
        $this->get(route('lt.edit', $version->template->uuid))->assertInertia(fn(Assert $page) => $page->where('document.data.state', 'stale'));
    }

    #[DataProvider('placeholderErrors')]
    public function test_reports_invalid_placeholders_and_clears_prior_evidence(string $text): void
    {
        Storage::fake('local');
        $version = LetterTemplateVersion::factory()->create();
        $this->actingAs(UserFactory::new()->create())->post($this->route($version, 'source.store'), ['docx' => $this->docx($text)])->assertSessionHasNoErrors();
        $version->refresh()->update(['validated_at' => now(), 'tested_at' => now(), 'visually_confirmed_at' => now()]);

        $this->post($this->route($version, 'validation.store'))->assertInvalid(['docx']);

        $stored = $version->fresh();
        $this->assertFalse($stored->validation_result['valid']);
        $this->assertNotEmpty($stored->validation_result['errors']);
        $this->assertNull($stored->validated_at);
        $this->assertNull($stored->tested_at);
        $this->assertNull($stored->visually_confirmed_at);
    }

    public static function placeholderErrors(): array
    {
        return [['${tidak_dikenal}'], ['${belum_selesai'], ['${Nama Salah}'], ['${' . str_repeat('a', 101) . '}']];
    }

    public function test_signer_and_variable_placeholders_may_be_unused_and_signer_attributes_work_without_name(): void
    {
        Storage::fake('local');
        $version = LetterTemplateVersion::factory()->create();
        LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'tidak_dipakai']);
        LetterTemplateSignerSlot::factory()->for($version, 'version')->create();
        $this->actingAs(UserFactory::new()->create())->post($this->route($version, 'source.store'), ['docx' => $this->docx('Tanpa penandatangan')])->assertSessionHasNoErrors();
        $this->post($this->route($version, 'validation.store'))->assertSessionHasNoErrors();
        $this->assertTrue($version->fresh()->validation_result['valid']);
        $this->post($this->route($version, 'source.store'), ['docx' => $this->docx('${penandatangan_pangkat} ${penandatangan_golongan}')])->assertSessionHasNoErrors();
        $this->post($this->route($version, 'validation.store'))->assertSessionHasNoErrors();
        $this->post($this->route($version, 'source.store'), ['docx' => $this->docx('${penandatangan}')])->assertSessionHasNoErrors();
        $this->post($this->route($version, 'validation.store'))->assertSessionHasNoErrors();
        $this->assertTrue($version->fresh()->validation_result['valid']);
    }

    public function test_scalar_employee_attribute_tokens_validate_in_document_headers_and_footers(): void
    {
        Storage::fake('local');
        $version = LetterTemplateVersion::factory()->create();
        LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'pegawai', 'type' => 4, 'option_source' => 2, 'is_multiple' => false]);
        $this->actingAs(UserFactory::new()->create())->post($this->route($version, 'source.store'), ['docx' => $this->docx('${pegawai_pangkat} ${pegawai_golongan} ${pegawai_masa_kerja} ${pegawai_tanggal_bergabung}', headerFooter: true)])->assertSessionHasNoErrors();
        $this->post($this->route($version, 'validation.store'))->assertSessionHasNoErrors();
        $this->assertTrue($version->fresh()->validation_result['valid']);
    }

    public function test_existing_configuration_with_new_automatic_token_collision_must_be_fixed_before_validation(): void
    {
        Storage::fake('local');
        $version = LetterTemplateVersion::factory()->create();
        LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'pegawai', 'type' => 4, 'option_source' => 2, 'is_multiple' => false]);
        LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'pegawai_nip']);
        $this->actingAs(UserFactory::new()->create())->post($this->route($version, 'source.store'), ['docx' => $this->docx('${pegawai_nip}')])->assertSessionHasNoErrors();
        $this->post($this->route($version, 'validation.store'))->assertInvalid(['docx' => 'Konfigurasi memiliki placeholder yang bertabrakan. Perbaiki nama variabel atau kolom sebelum validasi.']);
        $this->assertNull($version->fresh()->validated_at);
    }

    #[DataProvider('dangerousPackages')]
    public function test_rejects_unsafe_or_unsupported_packages_and_preserves_existing_source(string $kind): void
    {
        Storage::fake('local');
        $version = LetterTemplateVersion::factory()->create(['source_disk' => 'local', 'source_path' => 'old.docx', 'source_sha256' => 'old']);
        Storage::disk('local')->put('old.docx', 'old');
        $file = match ($kind) {
            'not zip' => UploadedFile::fake()->createWithContent('bad.docx', 'not a docx'),
            'missing parts' => $this->docx('Text', ['word/document.xml' => null]),
            'traversal' => $this->docx('Text', ['../escape.xml' => '<x/>']),
            'macro' => $this->docx('Text', ['word/vbaProject.bin' => 'macro']),
            'embedded' => $this->docx('Text', ['word/embeddings/oleObject1.bin' => 'object']),
            'entity' => $this->docx('Text', ['customXml/item1.xml' => '<!DOCTYPE x [<!ENTITY ext SYSTEM "file:///etc/passwd">]><x>&ext;</x>']),
            'invalid xml' => $this->docx('Text', ['customXml/item1.xml' => '<not-closed>']),
            'external' => $this->docx('Text', ['word/_rels/document.xml.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="x" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="https://example.test/pixel.png" TargetMode="External"/></Relationships>']),
            'field' => $this->docx('Text', ['word/document.xml' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:instrText>DDEAUTO cmd /c echo test</w:instrText></w:r></w:p></w:body></w:document>']),
            'svg' => $this->docx('Text', ['word/media/image.svg' => '<svg/>']),
            'utf16 entity' => $this->docx('Text', ['word/unsafe.xml' => mb_convert_encoding('<?xml version="1.0" encoding="UTF-16"?><!DOCTYPE x [<!ENTITY ext SYSTEM "file:///etc/passwd">]><x/>', 'UTF-16', 'UTF-8')]),
            'uppercase xml' => $this->docx('Text', ['word/unsafe.XML' => '<!DOCTYPE x [<!ENTITY ext SYSTEM "file:///etc/passwd">]><x/>']),
            'header gap' => $this->docx('Text', ['word/header2.xml' => '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>']),
            'zip limits' => $this->docx('Text', ['customXml/item1.xml' => '<x>' . str_repeat('A', 200000) . '</x>']),
        };
        $this->actingAs(UserFactory::new()->create())->post($this->route($version, 'source.store'), ['docx' => $file])->assertInvalid(['docx']);
        $this->assertSame('old.docx', $version->fresh()->source_path);
        Storage::disk('local')->assertExists('old.docx');
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public static function dangerousPackages(): array
    {
        return array_map(fn(string $kind): array => [$kind], ['not zip', 'missing parts', 'traversal', 'macro', 'embedded', 'entity', 'invalid xml', 'external', 'field', 'svg', 'utf16 entity', 'uppercase xml', 'header gap', 'zip limits']);
    }

    public function test_size_extension_and_missing_upload_are_rejected(): void
    {
        Storage::fake('local');
        $version = LetterTemplateVersion::factory()->create();
        $this->actingAs(UserFactory::new()->create());
        foreach ([null, UploadedFile::fake()->create('wrong.pdf', 1), UploadedFile::fake()->create('huge.docx', 10241)] as $file) {
            $this->post($this->route($version, 'source.store'), ['docx' => $file])->assertInvalid(['docx']);
        }
        Storage::disk('local')->assertEmpty();
    }

    public function test_replacement_removes_unreferenced_draft_file_and_keeps_source_shared_with_history(): void
    {
        Storage::fake('local');
        $version = LetterTemplateVersion::factory()->create(['version_number' => 2]);
        $this->actingAs(UserFactory::new()->create())->post($this->route($version, 'source.store'), ['docx' => $this->docx('Original')])->assertSessionHasNoErrors();
        $old = $version->fresh()->source_path;
        $historical = LetterTemplateVersion::factory()->for($version->template, 'template')->create(['version_number' => 1, 'status' => LetterTemplateVersionStatusEnum::PUBLISHED, 'first_used_at' => now(), 'source_disk' => 'local', 'source_path' => $old]);
        $this->post($this->route($version, 'source.store'), ['docx' => $this->docx('Replacement')])->assertSessionHasNoErrors();
        $second = $version->fresh()->source_path;
        $this->assertNotSame($old, $second);
        Storage::disk('local')->assertExists($old);
        $this->assertSame($old, $historical->fresh()->source_path);
        $this->post($this->route($version, 'source.store'), ['docx' => $this->docx('Third')])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($second);
        Storage::disk('local')->assertExists($old);
    }

    public function test_missing_and_tampered_storage_cannot_produce_valid_proof(): void
    {
        Storage::fake('local');
        $version = LetterTemplateVersion::factory()->create();
        $this->actingAs(UserFactory::new()->create())->post($this->route($version, 'source.store'), ['docx' => $this->docx()])->assertSessionHasNoErrors();
        Storage::disk('local')->put($version->fresh()->source_path, 'tampered');
        $this->post($this->route($version, 'validation.store'))->assertInvalid(['docx']);
        $this->assertFalse($version->fresh()->validation_result['valid']);
        Storage::disk('local')->delete($version->fresh()->source_path);
        $this->post($this->route($version, 'validation.store'))->assertInvalid(['docx']);
        $this->assertNull($version->fresh()->validated_source_sha256);
    }

    public function test_database_failure_cleans_new_upload_without_deleting_old_source(): void
    {
        Storage::fake('local');
        $version = LetterTemplateVersion::factory()->create(['source_path' => 'old.docx', 'source_disk' => 'local']);
        Storage::disk('local')->put('old.docx', 'old');
        $actor = UserFactory::new()->create();
        Exceptions::fake();
        LetterTemplateVersion::updating(function (): void { throw new RuntimeException('Simulated database failure'); });
        try {
            $this->actingAs($actor)->post($this->route($version, 'source.store'), ['docx' => $this->docx()])->assertInternalServerError();
        } finally {
            LetterTemplateVersion::getEventDispatcher()->forget('eloquent.updating: ' . LetterTemplateVersion::class);
        }
        Exceptions::assertReported(RuntimeException::class);
        $this->assertSame('old.docx', $version->fresh()->source_path);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_authenticated_source_download_and_unavailable_source(): void
    {
        Storage::fake('local');
        $version = LetterTemplateVersion::factory()->create();
        $file = $this->docx();
        $content = file_get_contents($file->getPathname());
        $this->actingAs(UserFactory::new()->create())->get($this->route($version, 'source.download'))->assertNotFound();
        $this->post($this->route($version, 'source.store'), ['docx' => $file])->assertSessionHasNoErrors();
        $this->get($this->route($version, 'source.download'))->assertOk()->assertDownload('source.docx')->assertStreamedContent($content);
    }

    public function test_all_document_endpoints_require_authentication_and_scope_version_binding(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $other = LetterTemplateVersion::factory()->create();
        foreach (['source.store' => 'post', 'source.download' => 'get', 'validation.store' => 'post', 'example.download' => 'get'] as $route => $method) {
            $this->$method($this->route($version, $route))->assertRedirectToRoute('login.index');
        }
        $this->actingAs(UserFactory::new()->create());
        foreach (['source.store' => 'post', 'source.download' => 'get', 'validation.store' => 'post', 'example.download' => 'get'] as $route => $method) {
            foreach ([$other->uuid, (string) Str::uuid(), $version->id] as $identifier) {
                $this->$method(route('lt.' . $route, [$version->template->uuid, $identifier]))->assertNotFound();
            }
        }
    }

    #[DataProvider('uneditableVersions')]
    public function test_upload_and_validation_cannot_change_historical_or_used_versions(LetterTemplateVersionStatusEnum $status, bool $used): void
    {
        Storage::fake('local');
        $version = LetterTemplateVersion::factory()->create(['status' => $status, 'first_used_at' => $used ? now() : null]);
        $this->actingAs(UserFactory::new()->create())->post($this->route($version, 'source.store'), ['docx' => $this->docx()])->assertInvalid(['docx']);
        $this->post($this->route($version, 'validation.store'))->assertInvalid(['docx']);
        Storage::disk('local')->assertEmpty();
    }

    public static function uneditableVersions(): array
    {
        return [[LetterTemplateVersionStatusEnum::PUBLISHED, false], [LetterTemplateVersionStatusEnum::ARCHIVED, false], [LetterTemplateVersionStatusEnum::DRAFT, true]];
    }

    #[DataProvider('zipLimits')]
    public function test_package_resource_limits_are_enforced(string $setting): void
    {
        Storage::fake('local');
        config(['letter-templates.zip.' . $setting => 1]);
        $version = LetterTemplateVersion::factory()->create();
        $this->actingAs(UserFactory::new()->create())->post($this->route($version, 'source.store'), ['docx' => $this->docx()])->assertInvalid(['docx']);
        Storage::disk('local')->assertEmpty();
    }

    public static function zipLimits(): array
    {
        return [['max_entries'], ['max_uncompressed_bytes'], ['max_xml_bytes'], ['max_xml_total_bytes'], ['max_compression_ratio']];
    }

    public function test_failed_storage_preserves_existing_source(): void
    {
        $version = LetterTemplateVersion::factory()->create(['source_path' => 'old.docx', 'source_disk' => 'local']);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        $this->actingAs(UserFactory::new()->create())->post($this->route($version, 'source.store'), ['docx' => $this->docx()])->assertInvalid(['docx' => 'DOCX gagal disimpan. Silakan coba lagi.']);
        $this->assertSame('old.docx', $version->fresh()->source_path);
    }

    public function test_missing_source_and_excess_placeholder_count_cannot_validate(): void
    {
        Storage::fake('local');
        $version = LetterTemplateVersion::factory()->create();
        $this->actingAs(UserFactory::new()->create())->post($this->route($version, 'validation.store'))->assertInvalid(['docx' => 'Unggah DOCX sumber terlebih dahulu.']);
        $this->post($this->route($version, 'source.store'), ['docx' => $this->docx('${nama}')])->assertSessionHasNoErrors();
        config(['letter-templates.placeholders.max_occurrences' => 0]);
        $this->post($this->route($version, 'validation.store'))->assertInvalid(['docx']);
        $this->assertNull($version->fresh()->validated_at);
    }

    public function test_multiple_columns_must_belong_to_one_body_table_row(): void
    {
        Storage::fake('local');
        $version = LetterTemplateVersion::factory()->create();
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'pegawai', 'type' => 4, 'option_source' => 2, 'is_multiple' => true]);
        LetterTemplateVariableColumn::factory()->for($variable, 'variable')->create(['source_attribute' => 'name', 'placeholder' => 'pegawai_nama']);
        $this->actingAs(UserFactory::new()->create())->post($this->route($version, 'source.store'), ['docx' => $this->docx('Tabel', [], false, false, [['pegawai_nama']])])->assertSessionHasNoErrors();
        $this->post($this->route($version, 'validation.store'))->assertSessionHasNoErrors();
        $this->post($this->route($version, 'source.store'), ['docx' => $this->docx('${pegawai_nama}')])->assertSessionHasNoErrors();
        $this->post($this->route($version, 'validation.store'))->assertInvalid(['docx']);
        $this->post($this->route($version, 'source.store'), ['docx' => $this->docx('Tabel', [], false, false, [['pegawai_nama'], ['pegawai_nama']])])->assertSessionHasNoErrors();
        $this->post($this->route($version, 'validation.store'))->assertInvalid(['docx']);
    }

    public function test_placeholders_in_unsupported_document_parts_are_reported(): void
    {
        Storage::fake('local');
        $version = LetterTemplateVersion::factory()->create();
        $file = $this->docx('Contoh', ['word/footnotes.xml' => '<w:footnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:footnote><w:p><w:r><w:t>${catatan}</w:t></w:r></w:p></w:footnote></w:footnotes>']);
        $this->actingAs(UserFactory::new()->create())->post($this->route($version, 'source.store'), ['docx' => $file])->assertSessionHasNoErrors();
        $this->post($this->route($version, 'validation.store'))->assertInvalid(['docx']);
        $this->assertStringContainsString('word/footnotes.xml', $version->fresh()->validation_result['errors'][0]);
    }

    #[DataProvider('zipSpecialEntries')]
    public function test_encrypted_symlink_and_duplicate_entries_are_rejected(string $kind): void
    {
        Storage::fake('local');
        $file = $this->docx('Contoh', ['word/dupe_a.xml' => '<x/>', 'word/dupe_b.xml' => '<x/>']);
        $path = $file->getPathname();
        $zip = new ZipArchive();
        $zip->open($path);
        if ($kind === 'symlink') {
            $zip->addFromString('word/link', '/etc/passwd');
            $this->assertTrue($zip->setExternalAttributesName('word/link', ZipArchive::OPSYS_UNIX, 0120777 << 16));
        } elseif ($kind === 'encrypted') {
            $this->assertTrue($zip->setEncryptionName('word/document.xml', ZipArchive::EM_AES_256, 'secret'));
        }
        $zip->close();
        if ($kind === 'duplicate') {
            file_put_contents($path, str_replace('word/dupe_b.xml', 'word/dupe_a.xml', file_get_contents($path)));
        }
        $version = LetterTemplateVersion::factory()->create();
        $this->actingAs(UserFactory::new()->create())->post($this->route($version, 'source.store'), ['docx' => $file])->assertInvalid(['docx']);
        Storage::disk('local')->assertEmpty();
    }

    public static function zipSpecialEntries(): array
    {
        return [['symlink'], ['encrypted'], ['duplicate']];
    }

    public function test_configuration_change_preserves_source_and_requires_validation_again(): void
    {
        Storage::fake('local');
        $version = LetterTemplateVersion::factory()->create();
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'nama']);
        $this->actingAs(UserFactory::new()->create())->post($this->route($version, 'source.store'), ['docx' => $this->docx('${nama}')])->assertSessionHasNoErrors();
        $this->post($this->route($version, 'validation.store'))->assertSessionHasNoErrors();
        $path = $version->fresh()->source_path;
        $hash = $version->fresh()->source_sha256;

        $this->put(route('lt.configuration.update', [$version->template->uuid, $version->uuid]), ['variables' => [[
            'id' => $variable->uuid, 'name' => 'baru', 'label' => 'Nama Baru', 'type' => 1, 'is_required' => false,
            'option_source' => null, 'reference_group' => null, 'is_multiple' => false, 'columns' => [],
        ]], 'signer_slots' => []])->assertSessionHasNoErrors();

        $this->assertSame($path, $version->fresh()->source_path);
        $this->assertNull($version->fresh()->validated_at);
        Storage::disk('local')->assertExists($path);
        $this->post($this->route($version, 'validation.store'))->assertInvalid(['docx']);
        $this->post($this->route($version, 'source.store'), ['docx' => $this->docx('${baru}')])->assertSessionHasNoErrors();
        $this->assertNotSame($hash, $version->fresh()->source_sha256);
        $this->post($this->route($version, 'validation.store'))->assertSessionHasNoErrors();
    }

    public function test_pinned_phpword_replaces_split_runs_headers_footers_long_text_and_clones_rows(): void
    {
        $file = $this->docx('${nama}', [], true, true, [['pegawai_nama']]);
        $path = tempnam(sys_get_temp_dir(), 'processed-docx-');
        try {
            $processor = new LetterTemplateProcessor($file->getPathname());
            $processor->setValue('nama', str_repeat('Keterangan panjang ', 200) . "\nBaris kedua");
            $processor->cloneRowAndSetValues('pegawai_nama', [['pegawai_nama' => 'Pegawai Contoh Satu'], ['pegawai_nama' => 'Pegawai Contoh Dua']]);
            $processor->saveAs($path);
            unset($processor);
            $parts = app(InspectLetterTemplateDocx::class)->read($path);
            foreach (['word/document.xml', 'word/header1.xml', 'word/footer1.xml'] as $part) {
                $this->assertStringContainsString('Baris kedua', $parts[$part]->textContent);
                $this->assertStringNotContainsString('${nama}', $parts[$part]->textContent);
            }
            $this->assertStringContainsString('Pegawai Contoh Satu', $parts['word/document.xml']->textContent);
            $this->assertStringContainsString('Pegawai Contoh Dua', $parts['word/document.xml']->textContent);
        } finally {
            unlink($path);
        }
    }

    private function route(LetterTemplateVersion $version, string $name): string
    {
        return route('lt.' . $name, [$version->template->uuid, $version->uuid]);
    }

    private function docx(string $text = 'Contoh dokumen', array $overrides = [], bool $headerFooter = false, bool $split = false, array $rows = []): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'docx-test-');
        try {
            $word = new PhpWord();
            $section = $word->addSection();
            $section->addText($text);
            if ($rows !== []) {
                $table = $section->addTable();
                foreach ($rows as $row) {
                    $table->addRow();
                    foreach ($row as $name) {
                        $table->addCell(2000)->addText('${' . $name . '}');
                    }
                }
            }
            if ($headerFooter) {
                $section->addHeader()->addText($text);
                $section->addFooter()->addText($text);
            }
            IOFactory::createWriter($word, 'Word2007')->save($path);
            $zip = new ZipArchive();
            $zip->open($path);
            if ($split) {
                $zip->addFromString('word/document.xml', str_replace('${nama}', '${na</w:t></w:r><w:r><w:t>ma}', $zip->getFromName('word/document.xml')));
            }
            foreach ($overrides as $name => $content) {
                if ($content === null) {
                    $zip->deleteName($name);
                } else {
                    $zip->addFromString($name, $content);
                }
            }
            $zip->close();

            return UploadedFile::fake()->createWithContent('source.docx', file_get_contents($path));
        } finally {
            unlink($path);
        }
    }
}
