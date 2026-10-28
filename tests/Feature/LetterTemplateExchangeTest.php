<?php

namespace Tests\Feature;

use App\Enums\AsnStatusEnum;
use App\Enums\LetterTemplateOptionSourceEnum;
use App\Enums\LetterTemplateVariableTypeEnum;
use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\DocumentFont;
use App\Models\MasterData\Employee;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVersion;
use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\User;
use App\Services\Letter\DocumentFontRegistry;
use App\Services\Letter\InstallDocumentFont;
use App\Services\LetterTemplate\LetterTemplateExchangeConfiguration;
use App\Services\LetterTemplate\LetterTemplateExchangePackage;
use App\Services\LetterTemplate\LetterTemplateExchangeStage;
use App\Services\LetterTemplate\LetterTemplateVersionState;
use App\Services\LetterTemplate\RecordLetterTemplateAudit;
use App\Services\LetterTemplate\TnexCipher;
use Database\Factories\LetterNumberFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class LetterTemplateExchangeTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string PASSWORD = 'Paket-rahasia-12345';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    public function test_http_workflow_preserves_configuration_and_original_docx_but_requires_local_publication_evidence(): void
    {
        $actor = UserFactory::new()->create();
        $this->actingAs($actor);
        $source = $this->source();
        $number = LetterNumberFactory::new()->create();
        $original = app(LetterTemplateExchangeConfiguration::class)->export($source);
        $args = [$source->template->uuid, $source->uuid];
        $this->get(route('lt.exchange.export-page', $args))->assertInertia(fn(Assert $page) => $page->where('mode', 'export'));
        $this->postJson(route('lt.exchange.estimate', $args), ['include_docx' => true, 'include_fonts' => false])->assertOk()->assertJsonPath('font_bytes', 0)->assertJsonPath('missing_fonts', []);
        $response = $this->postJson(route('lt.exchange.export', $args), $this->exportData())->assertOk();
        $download = $this->get($response->json('download_url'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame($response->json('size'), strlen($download->getContent()));
        $this->assertStringNotContainsString($source->template->name, $download->getContent());
        $this->get($response->json('download_url'))->assertNotFound();
        config(['app.key' => 'base64:' . base64_encode(random_bytes(32))]);
        $inspection = $this->postJson(route('lt.exchange.inspect'), ['package' => UploadedFile::fake()->createWithContent('templat.tnex', $download->getContent()), 'password' => self::PASSWORD])->assertOk();
        $this->get($inspection->json('review_url'))->assertInertia(fn(Assert $page) => $page->where('review.has_docx', true)->where('review.incompatibilities', [])->where('mode', 'review'));
        $token = basename($inspection->json('review_url'));
        $this->post(route('lt.exchange.store', $token), $this->mapping($number->uuid))->assertSessionHasNoErrors();
        $imported = LetterTemplate::query()->where('name', 'Templat Hasil Impor')->sole()->draftVersion;
        $this->assertSame($original, app(LetterTemplateExchangeConfiguration::class)->export($imported));
        $this->assertSame(Storage::disk('local')->get($source->source_path), Storage::disk('local')->get($imported->source_path));
        $this->assertSame('j F Y', $imported->letter_date_format);
        $this->assertNull($imported->validated_at);
        $this->assertNull($imported->tested_at);
        $this->assertNull($imported->visually_confirmed_at);
        $this->assertNull($imported->template->active_version_id);
        $this->assertSame(LetterTemplateVersionStatusEnum::DRAFT, $imported->status);
        $this->assertDatabaseHas('letter_template_audits', ['version_id' => $imported->id, 'operation' => 'exchange.imported']);
        $this->get($inspection->json('review_url'))->assertNotFound();
        $this->assertSame([], Storage::disk('local')->directories(config('letter-templates.exchange.directory')));
    }

    #[DataProvider('inclusionOptions')]
    public function test_all_docx_and_font_options_preserve_only_the_required_tahoma_file(bool $docx, bool $fonts): void
    {
        $actor = UserFactory::new()->create();
        $this->actingAs($actor);
        $source = $this->source();
        $font = $this->tahoma();
        $package = app(LetterTemplateExchangePackage::class);
        $prepared = $package->prepare($source->template, $source, $docx, $fonts);
        $this->assertSame([], $prepared['missing_fonts']);
        $this->assertCount(($docx ? 1 : 0) + ($fonts ? 1 : 0), $prepared['files']);
        $decoded = $package->decode($package->encode($prepared, self::PASSWORD), self::PASSWORD);
        $this->assertSame($prepared['files'], $decoded['files']);
        $this->assertSame($docx, isset($decoded['files']['source.docx']));
        if ($fonts) {
            $this->assertSame($font, $decoded['files']['fonts/' . hash('sha256', $font) . '.ttf']);
            $this->assertSame('Tahoma', $decoded['manifest']['font_requirements'][0]['family']);
        }
        $token = app(LetterTemplateExchangeStage::class)->create($decoded, $actor);
        $this->post(route('lt.exchange.store', $token), $this->mapping(LetterNumberFactory::new()->create()->uuid))->assertSessionHasNoErrors();
        $imported = LetterTemplate::query()->where('name', 'Templat Hasil Impor')->sole()->draftVersion;
        $this->assertSame($docx, $imported->source_path !== null);
        $this->assertDatabaseCount('document_fonts', $fonts ? 1 : 0);
        if ($fonts) {
            $installed = DocumentFont::query()->sole();
            $this->assertSame($font, Storage::disk($installed->disk)->get($installed->path));
        }
    }

    public static function inclusionOptions(): array
    {
        return ['configuration' => [false, false], 'docx' => [true, false], 'fonts without docx' => [false, true], 'docx and fonts' => [true, true]];
    }

    public function test_missing_font_blocks_export_and_lists_the_missing_original_instead_of_packaging_a_fallback(): void
    {
        $this->actingAs(UserFactory::new()->create());
        $this->isolatedFonts();
        $source = $this->source();
        $args = [$source->template->uuid, $source->uuid];
        $this->postJson(route('lt.exchange.estimate', $args), ['include_docx' => false, 'include_fonts' => true])->assertOk()->assertJsonPath('missing_fonts.0', 'Tahoma Regular')->assertJsonPath('font_bytes', 0);
        $this->postJson(route('lt.exchange.export', $args), [...$this->exportData(), 'include_fonts' => true])->assertUnprocessable()->assertJsonValidationErrors('package');
        $this->assertDatabaseCount('document_fonts', 0);
    }

    #[DataProvider('brokenCiphertexts')]
    public function test_wrong_password_truncated_tampered_or_unknown_format_returns_422_and_leaves_no_staging(string $damage): void
    {
        $this->actingAs(UserFactory::new()->create());
        $source = $this->source();
        $bytes = $this->encoded($source);
        $password = self::PASSWORD;
        if ($damage === 'password') {
            $password = 'Kata-sandi-salah-12345';
        } elseif ($damage === 'truncated') {
            $bytes = substr($bytes, 0, -1);
        } elseif ($damage === 'tampered') {
            $bytes[strlen($bytes) - 2] = chr(ord($bytes[strlen($bytes) - 2]) ^ 1);
        } else {
            $bytes[4] = "\x02";
        }
        $this->postJson(route('lt.exchange.inspect'), ['package' => UploadedFile::fake()->createWithContent('x.tnex', $bytes), 'password' => $password])->assertUnprocessable()->assertJsonValidationErrors('package');
        $this->assertSame([], Storage::disk('local')->directories(config('letter-templates.exchange.directory')));
    }

    public static function brokenCiphertexts(): array
    {
        return array_map(fn(string $damage): array => [$damage], ['password', 'truncated', 'tampered', 'unknown']);
    }

    public function test_cipher_uses_fresh_salts_and_rejects_missing_final_frames_and_appended_data(): void
    {
        $cipher = app(TnexCipher::class);
        $payload = str_repeat('x', 140000);
        $first = $cipher->encrypt($payload, self::PASSWORD);
        $this->assertNotSame($first, $cipher->encrypt($payload, self::PASSWORD));
        $this->assertSame($payload, $cipher->decrypt($first, self::PASSWORD));
        $this->actingAs(UserFactory::new()->create());
        foreach ([substr($first, 0, 45 + 65536 + 17), $first . 'append'] as $broken) {
            $this->postJson(route('lt.exchange.inspect'), ['package' => UploadedFile::fake()->createWithContent('x.tnex', $broken), 'password' => self::PASSWORD])->assertUnprocessable();
        }
    }

    #[DataProvider('invalidPackages')]
    public function test_authenticated_but_invalid_payload_is_rejected_without_extraction(string $damage): void
    {
        $this->actingAs(UserFactory::new()->create());
        $source = $this->source();
        $prepared = app(LetterTemplateExchangePackage::class)->prepare($source->template, $source, true, false);
        if ($damage === 'manifest-version') {
            $prepared['manifest']['version'] = 999;
        } elseif ($damage === 'checksum') {
            $prepared['manifest']['files']['source.docx']['sha256'] = str_repeat('0', 64);
        } elseif ($damage === 'unknown-entry') {
            $prepared['files']['../escape.php'] = 'bad';
        } elseif ($damage === 'configuration') {
            $prepared['manifest']['configuration']['variables'][0]['name'] = 'INVALID';
        } elseif ($damage === 'docx') {
            $prepared['files']['source.docx'] = 'not docx';
            $prepared['manifest']['files']['source.docx'] = ['size' => 8, 'sha256' => hash('sha256', 'not docx')];
        } elseif ($damage === 'active-docx') {
            $path = Storage::disk('local')->path($source->source_path);
            $zip = new ZipArchive();
            $zip->open($path);
            $zip->addFromString('word/vbaProject.bin', 'macro');
            $zip->close();
            $bytes = file_get_contents($path);
            $prepared['files']['source.docx'] = $bytes;
            $prepared['manifest']['files']['source.docx'] = ['size' => strlen($bytes), 'sha256' => hash('sha256', $bytes)];
        } elseif ($damage === 'configuration-shape') {
            $prepared['manifest']['configuration']['variables'][0]['columns'] = 'invalid';
        } elseif ($damage === 'invalid-font') {
            $name = 'fonts/' . hash('sha256', 'not a font') . '.ttf';
            $prepared['files'][$name] = 'not a font';
            $prepared['manifest']['files'][$name] = ['size' => 10, 'sha256' => hash('sha256', 'not a font')];
        } elseif ($damage === 'entries') {
            config(['letter-templates.exchange.max_entries' => 1]);
        } else {
            config(['letter-templates.exchange.max_bytes' => 100]);
        }
        $bytes = $damage === 'size' ? str_repeat('x', 101) : app(LetterTemplateExchangePackage::class)->encode($prepared, self::PASSWORD);
        $this->postJson(route('lt.exchange.inspect'), ['package' => UploadedFile::fake()->createWithContent('x.tnex', $bytes), 'password' => self::PASSWORD])->assertUnprocessable();
        $this->assertSame([], Storage::disk('local')->directories(config('letter-templates.exchange.directory')));
        Storage::disk('local')->assertMissing('escape.php');
    }

    public static function invalidPackages(): array
    {
        return array_map(fn(string $damage): array => [$damage], ['manifest-version', 'checksum', 'unknown-entry', 'configuration', 'docx', 'active-docx', 'configuration-shape', 'invalid-font', 'entries', 'size']);
    }

    public function test_local_reference_and_signer_mappings_are_required_and_source_personal_data_is_absent(): void
    {
        $actor = UserFactory::new()->create();
        $this->actingAs($actor);
        $source = $this->source();
        $group = ReferenceGroup::factory()->create(['name' => 'Grup sumber rahasia']);
        $employee = Employee::factory()->create(['name' => 'Pegawai sumber rahasia', 'status' => AsnStatusEnum::AKTIF]);
        LetterTemplateVariable::factory()->create(['version_id' => $source->id, 'name' => 'pilihan', 'type' => LetterTemplateVariableTypeEnum::COMBOBOX, 'option_source' => LetterTemplateOptionSourceEnum::REFERENCE, 'reference_group_id' => $group->id]);
        $slot = $source->signerSlots()->create(['sort_order' => 1]);
        $slot->candidates()->create(['employee_id' => $employee->id]);
        $slot->employeeAttributes()->create(['source_attribute' => 'join_date', 'default_text' => 'Belum tersedia', 'date_format' => 'j F Y']);
        $multiple = LetterTemplateVariable::factory()->create(['version_id' => $source->id, 'name' => 'daftar_pegawai', 'type' => LetterTemplateVariableTypeEnum::COMBOBOX, 'option_source' => LetterTemplateOptionSourceEnum::EMPLOYEE, 'is_multiple' => true]);
        $multiple->columns()->create(['source_attribute' => 'join_date', 'placeholder' => 'tanggal_bergabung', 'date_format' => 'd/m/Y', 'sort_order' => 0]);
        $single = LetterTemplateVariable::factory()->create(['version_id' => $source->id, 'name' => 'pegawai', 'type' => LetterTemplateVariableTypeEnum::COMBOBOX, 'option_source' => LetterTemplateOptionSourceEnum::EMPLOYEE]);
        $single->employeeAttributes()->create(['source_attribute' => 'sip_expired_date', 'default_text' => 'Tidak ada', 'date_format' => 'j F Y']);
        $prepared = app(LetterTemplateExchangePackage::class)->prepare($source->template, $source, false, false);
        $json = json_encode($prepared['manifest']);
        $this->assertStringNotContainsString($employee->uuid, $json);
        $this->assertStringNotContainsString($employee->name, $json);
        $this->assertStringNotContainsString($group->uuid, $json);
        $this->assertStringNotContainsString($group->name, $json);
        $decoded = app(LetterTemplateExchangePackage::class)->decode(app(LetterTemplateExchangePackage::class)->encode($prepared, self::PASSWORD), self::PASSWORD);
        $this->assertSame([], $decoded['incompatibilities']);
        $token = app(LetterTemplateExchangeStage::class)->create($decoded, $actor);
        $number = LetterNumberFactory::new()->create();
        $this->post(route('lt.exchange.store', $token), $this->mapping($number->uuid))->assertInvalid('signer_slots.0.candidates');
        $localGroup = ReferenceGroup::factory()->create();
        $localEmployee = Employee::factory()->create(['status' => AsnStatusEnum::AKTIF]);
        $mapping = [...$this->mapping($number->uuid), 'reference_groups' => ['pilihan' => $localGroup->uuid], 'signers' => [[$localEmployee->uuid]]];
        $this->post(route('lt.exchange.store', $token), [...$mapping, 'reference_groups' => []])->assertInvalid('variables.1.reference_group');
        $localEmployee->update(['status' => AsnStatusEnum::PENSIUN]);
        $this->post(route('lt.exchange.store', $token), $mapping)->assertInvalid('signer_slots.0.candidates.0');
        $localEmployee->update(['status' => AsnStatusEnum::AKTIF]);
        $this->post(route('lt.exchange.store', $token), $mapping)->assertSessionHasNoErrors();
        $imported = LetterTemplate::query()->where('name', 'Templat Hasil Impor')->sole()->draftVersion;
        $this->assertSame($localGroup->id, $imported->variables()->where('name', 'pilihan')->sole()->reference_group_id);
        $this->assertSame($localEmployee->id, $imported->signerSlots->sole()->candidates->sole()->employee_id);
        $this->assertSame($prepared['manifest']['configuration'], app(LetterTemplateExchangeConfiguration::class)->export($imported));
    }

    public function test_attribute_type_or_dependency_mismatch_blocks_preview_and_save(): void
    {
        $actor = UserFactory::new()->create();
        $this->actingAs($actor);
        $source = $this->source();
        LetterTemplateVariable::factory()->create(['version_id' => $source->id, 'name' => 'pegawai', 'type' => LetterTemplateVariableTypeEnum::COMBOBOX, 'option_source' => LetterTemplateOptionSourceEnum::EMPLOYEE]);
        $prepared = app(LetterTemplateExchangePackage::class)->prepare($source->template, $source, false, false);
        $this->assertArrayHasKey('employees.join_date', $prepared['manifest']['employee_contract']['tenure']['dependencies']);
        $this->assertArrayHasKey('job_titles.name', $prepared['manifest']['employee_contract']['job_title']['dependencies']);
        $prepared['manifest']['employee_contract']['job_title']['dependencies']['job_titles.name'] = 'missing';
        $decoded = app(LetterTemplateExchangePackage::class)->decode(app(LetterTemplateExchangePackage::class)->encode($prepared, self::PASSWORD), self::PASSWORD);
        $this->assertNotEmpty($decoded['incompatibilities']);
        $token = app(LetterTemplateExchangeStage::class)->create($decoded, $actor);
        $this->get(route('lt.exchange.review', $token))->assertInertia(fn(Assert $page) => $page->has('review.incompatibilities', 1));
        $this->post(route('lt.exchange.store', $token), $this->mapping(LetterNumberFactory::new()->create()->uuid))->assertInvalid('package');
        $this->assertDatabaseMissing('letter_templates', ['name' => 'Templat Hasil Impor']);
    }

    public function test_replacing_a_draft_requires_confirmation_and_current_token_and_removes_docx_when_omitted(): void
    {
        $actor = UserFactory::new()->create();
        $this->actingAs($actor);
        $source = $this->source();
        $destination = $this->source();
        $oldPath = $destination->source_path;
        $token = $this->stage($source, $actor, false);
        $mapping = [...$this->mapping($destination->letterNumber->uuid), 'target' => $destination->template->uuid, 'name' => null];
        $this->post(route('lt.exchange.store', $token), $mapping)->assertInvalid('replace_draft');
        $versionToken = app(LetterTemplateVersionState::class)->token($destination);
        $destination->variables()->first()->update(['label' => 'Perubahan serentak']);
        $mapping = [...$mapping, 'replace_draft' => true, 'version_token' => $versionToken];
        $this->post(route('lt.exchange.store', $token), $mapping)->assertInvalid('version');
        $this->assertSame($oldPath, $destination->fresh()->source_path);
        Storage::disk('local')->assertExists($oldPath);
        $mapping['version_token'] = app(LetterTemplateVersionState::class)->token($destination->fresh());
        $this->post(route('lt.exchange.store', $token), $mapping)->assertSessionHasNoErrors();
        $destination->refresh();
        $this->assertNull($destination->source_path);
        $this->assertNull($destination->source_sha256);
        $this->assertNull($destination->validated_at);
        Storage::disk('local')->assertMissing($oldPath);
        $this->post(route('lt.validation.store', [$destination->template->uuid, $destination->uuid]))->assertInvalid('docx');
        $this->assertSame(1, $destination->version_number);
    }

    public function test_existing_template_without_draft_receives_a_new_version_and_preserves_published_source(): void
    {
        $actor = UserFactory::new()->create();
        $this->actingAs($actor);
        $source = $this->source();
        $published = $this->source();
        $published->update(['status' => LetterTemplateVersionStatusEnum::PUBLISHED, 'published_at' => now()]);
        $published->template->update(['active_version_id' => $published->id]);
        $token = $this->stage($source, $actor);
        $this->post(route('lt.exchange.store', $token), [...$this->mapping($published->letterNumber->uuid), 'target' => $published->template->uuid, 'name' => null])->assertSessionHasNoErrors();
        $draft = $published->template->fresh()->draftVersion;
        $this->assertSame(2, $draft->version_number);
        $this->assertSame($published->id, $published->template->fresh()->active_version_id);
        Storage::disk('local')->assertExists($published->source_path);
    }

    public function test_stage_is_account_bound_single_use_cancelable_and_expires_after_thirty_minutes(): void
    {
        $this->freezeTime();
        $owner = UserFactory::new()->create();
        $source = $this->source();
        $token = $this->stage($source, $owner);
        $this->actingAs(UserFactory::new()->create());
        $this->get(route('lt.exchange.review', $token))->assertNotFound();
        $this->delete(route('lt.exchange.destroy', $token))->assertNotFound();
        $this->post(route('lt.exchange.store', $token), $this->mapping(LetterNumberFactory::new()->create()->uuid))->assertNotFound();
        $this->actingAs($owner)->delete(route('lt.exchange.destroy', $token))->assertRedirectToRoute('lt.index');
        $this->get(route('lt.exchange.review', $token))->assertNotFound();
        $expired = $this->stage($source, $owner);
        $this->travel(30)->minutes();
        $this->post(route('lt.exchange.store', $expired), $this->mapping(LetterNumberFactory::new()->create()->uuid))->assertInvalid('package');
        $this->assertSame([], Storage::disk('local')->directories(config('letter-templates.exchange.directory')));
        $this->stage($source, $owner);
        $this->travel(30)->minutes();
        $this->artisan('letter-templates:cleanup-exchanges')->assertExitCode(0);
        $this->assertSame([], Storage::disk('local')->directories(config('letter-templates.exchange.directory')));
    }

    public function test_failure_after_font_installation_rolls_back_new_fonts_configuration_and_docx_preserving_old_draft(): void
    {
        $actor = UserFactory::new()->create();
        $this->actingAs($actor);
        $source = $this->source();
        $this->tahoma();
        $destination = $this->source();
        $oldPath = $destination->source_path;
        $oldConfiguration = app(LetterTemplateExchangeConfiguration::class)->export($destination);
        $token = $this->stage($source, $actor, true, true);
        $this->partialMock(RecordLetterTemplateAudit::class)->shouldReceive('record')->withArgs(fn($template, $version, $actor, $operation): bool => $operation === 'exchange.imported')->once()->andThrow(new RuntimeException('Simulated write failure'));
        $this->withoutExceptionHandling();
        try {
            $this->post(route('lt.exchange.store', $token), [...$this->mapping($destination->letterNumber->uuid), 'target' => $destination->template->uuid, 'name' => null, 'replace_draft' => true, 'version_token' => app(LetterTemplateVersionState::class)->token($destination)]);
            $this->fail('The simulated audit failure must abort the import.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated write failure', $exception->getMessage());
        }
        $this->assertSame($oldPath, $destination->fresh()->source_path);
        $this->assertSame($oldConfiguration, app(LetterTemplateExchangeConfiguration::class)->export($destination->fresh()));
        $this->assertDatabaseCount('document_fonts', 0);
        $this->assertSame([], Storage::disk('local')->allFiles(config('document-fonts.directory')));
        $this->assertCount(2, Storage::disk('local')->allFiles(config('letter-templates.paths.sources')));
        $this->get(route('lt.exchange.review', $token))->assertOk();
    }

    public function test_font_conflicts_block_saving_and_identical_fonts_are_reused(): void
    {
        $actor = UserFactory::new()->create();
        $this->actingAs($actor);
        $source = $this->source();
        $bytes = $this->tahoma();
        $token = $this->stage($source, $actor, false, true);
        $installed = app(InstallDocumentFont::class)->installFile(Storage::disk('local')->path('font-fixtures/tahoma.ttf'), 'tahoma.ttf', $actor);
        $this->post(route('lt.exchange.store', $token), $this->mapping(LetterNumberFactory::new()->create()->uuid))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('document_fonts', 1);
        $this->assertSame($bytes, Storage::disk('local')->get($installed->path));
        $conflict = $this->stage($source, $actor, false, true);
        Storage::disk('local')->put($installed->path, $bytes . "\0");
        $installed->update(['sha256' => hash('sha256', $bytes . "\0")]);
        $this->get(route('lt.exchange.review', $conflict))->assertInertia(fn(Assert $page) => $page->has('review.incompatibilities', 1));
        $this->post(route('lt.exchange.store', $conflict), [...$this->mapping(LetterNumberFactory::new()->create()->uuid), 'name' => 'Templat Konflik'])->assertInvalid('font');
        $this->assertDatabaseMissing('letter_templates', ['name' => 'Templat Konflik']);
        $this->assertSame($bytes . "\0", Storage::disk('local')->get($installed->path));
    }

    public function test_authentication_route_scope_password_confirmation_and_changed_source_are_enforced(): void
    {
        $source = $this->source();
        $args = [$source->template->uuid, $source->uuid];
        $this->postJson(route('lt.exchange.inspect'))->assertUnauthorized();
        $this->postJson(route('lt.exchange.export', $args))->assertUnauthorized();
        $this->get(route('lt.exchange.create'))->assertRedirectToRoute('login.index');
        $this->actingAs(UserFactory::new()->create());
        $this->postJson(route('lt.exchange.export', $args), [...$this->exportData(), 'password_confirmation' => 'different'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson(route('lt.exchange.export', $args), [...$this->exportData(), 'password' => 'short', 'password_confirmation' => 'short'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $other = LetterTemplate::factory()->create();
        $this->postJson(route('lt.exchange.export', [$other->uuid, $source->uuid]), $this->exportData())->assertNotFound();
        Storage::disk('local')->put($source->source_path, 'changed');
        $this->postJson(route('lt.exchange.export', $args), $this->exportData())->assertUnprocessable()->assertJsonValidationErrors('package');
    }

    public function test_font_export_includes_required_regular_bold_italic_faces_and_excludes_unused_families(): void
    {
        $source = $this->source();
        $this->isolatedFonts();
        $disk = Storage::disk('local');
        foreach (['arial.ttf', 'arialbd.ttf', 'ariali.ttf', 'arialbi.ttf', 'calibri.ttf'] as $font) {
            $disk->put('font-fixtures/' . $font, file_get_contents(resource_path('fonts/documents/' . $font)));
        }
        $word = new PhpWord();
        $word->setDefaultFontName('Arial');
        $section = $word->addSection();
        foreach ([[false, false], [true, false], [false, true], [true, true]] as [$bold, $italic]) {
            $section->addText('Teks', ['name' => 'Arial', 'bold' => $bold, 'italic' => $italic]);
        }
        IOFactory::createWriter($word, 'Word2007')->save($disk->path($source->source_path));
        $source->update(['source_sha256' => hash_file('sha256', $disk->path($source->source_path)), 'source_size' => $disk->size($source->source_path)]);
        $packages = app(LetterTemplateExchangePackage::class);
        $prepared = $packages->prepare($source->template, $source, false, true);
        $this->assertCount(4, $prepared['files']);
        $this->assertSame(['Arial'], array_values(array_unique(array_column($prepared['manifest']['font_requirements'], 'family'))));
        $this->assertSame([], $prepared['missing_fonts']);
        $decoded = $packages->decode($packages->encode($prepared, self::PASSWORD), self::PASSWORD);
        $this->assertSame($prepared['files'], $decoded['files']);
    }

    public function test_modified_private_staging_is_discarded_before_persistence(): void
    {
        $actor = UserFactory::new()->create();
        $this->actingAs($actor);
        $source = $this->source();
        $token = $this->stage($source, $actor);
        $root = Storage::disk('local')->directories(config('letter-templates.exchange.directory'))[0];
        Storage::disk('local')->put($root . '/source.docx', 'tampered');
        $this->post(route('lt.exchange.store', $token), $this->mapping(LetterNumberFactory::new()->create()->uuid))->assertInvalid('package');
        $this->assertDatabaseMissing('letter_templates', ['name' => 'Templat Hasil Impor']);
        $this->assertSame([], Storage::disk('local')->directories(config('letter-templates.exchange.directory')));
    }

    private function source(): LetterTemplateVersion
    {
        $version = LetterTemplateVersion::factory()->create(['letter_date_format' => 'j F Y']);
        LetterTemplateVariable::factory()->create(['version_id' => $version->id, 'name' => 'tujuan', 'label' => 'Tujuan surat', 'default_text' => 'Alternatif']);
        $word = new PhpWord();
        $word->setDefaultFontName('Tahoma');
        $word->addSection()->addText('Tujuan: ${tujuan}');
        $path = config('letter-templates.paths.sources') . '/' . $version->uuid . '/source.docx';
        Storage::disk('local')->makeDirectory(dirname($path));
        IOFactory::createWriter($word, 'Word2007')->save(Storage::disk('local')->path($path));
        $version->update(['source_disk' => 'local', 'source_path' => $path, 'source_size' => Storage::disk('local')->size($path), 'source_sha256' => hash_file('sha256', Storage::disk('local')->path($path))]);

        return $version;
    }

    private function encoded(LetterTemplateVersion $source, bool $docx = true, bool $fonts = false): string
    {
        $packages = app(LetterTemplateExchangePackage::class);

        return $packages->encode($packages->prepare($source->template, $source, $docx, $fonts), self::PASSWORD);
    }

    private function stage(LetterTemplateVersion $source, User $actor, bool $docx = true, bool $fonts = false): string
    {
        $package = app(LetterTemplateExchangePackage::class)->decode($this->encoded($source, $docx, $fonts), self::PASSWORD);

        return app(LetterTemplateExchangeStage::class)->create($package, $actor);
    }

    private function exportData(): array
    {
        return ['include_docx' => true, 'include_fonts' => false, 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD];
    }

    private function mapping(string $number): array
    {
        return ['name' => 'Templat Hasil Impor', 'target' => null, 'letter_number' => $number, 'reference_groups' => [], 'signers' => [], 'replace_draft' => false, 'version_token' => null, 'confirmed' => true];
    }

    private function isolatedFonts(): void
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory('font-fixtures');
        $disk->put('font-system.conf', '<fontconfig><dir>' . htmlspecialchars($disk->path('font-fixtures'), ENT_XML1) . '</dir></fontconfig>');
        config(['document-fonts.system_config' => $disk->path('font-system.conf')]);
    }

    private function tahoma(): string
    {
        $this->isolatedFonts();
        $path = resource_path('fonts/documents/tahoma.ttf');
        if (!is_file($path)) {
            $path = trim(Process::run(['/usr/bin/fc-match', '--format', '%{file}', 'Tahoma'])->output());
        }
        $this->assertFileExists($path);
        $bytes = file_get_contents($path);
        Storage::disk('local')->put('font-fixtures/tahoma.ttf', $bytes);
        $registry = app(DocumentFontRegistry::class);
        $face = $registry->scan(Storage::disk('local')->path('font-fixtures/tahoma.ttf'));
        $this->assertSame('Tahoma', $face['family'], 'The exchange acceptance test needs the bundled Tahoma font.');
        $registry->inventory(true);

        return $bytes;
    }
}
