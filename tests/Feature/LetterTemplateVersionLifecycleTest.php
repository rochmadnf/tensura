<?php

namespace Tests\Feature;

use App\Enums\AsnStatusEnum;
use App\Enums\LetterTemplateVersionStatusEnum;
use App\Models\MasterData\Employee;
use App\Models\MasterData\LetterTemplateAudit;
use App\Models\MasterData\LetterTemplateSignerSlot;
use App\Models\MasterData\LetterTemplateVariable;
use App\Models\MasterData\LetterTemplateVariableColumn;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\LetterTemplate\InspectLetterTemplateDocx;
use App\Services\LetterTemplate\LetterTemplateTestEvidence;
use App\Services\LetterTemplate\LetterTemplateVersionState;
use Database\Factories\UserFactory;
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

class LetterTemplateVersionLifecycleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_publication_promotes_only_the_target_and_preserves_historical_configuration_files_and_counters(): void
    {
        $version = $this->ready(versionNumber: 2);
        $history = LetterTemplateVersion::factory()->for($version->template, 'template')->create(['version_number' => 1, 'status' => LetterTemplateVersionStatusEnum::PUBLISHED, 'first_used_at' => now(), 'source_path' => 'historical.docx']);
        $version->template->update(['active_version_id' => $history->id]);
        $source = Storage::disk('local')->get($version->source_path);
        $result = Storage::disk('local')->get($version->test_path);
        $counters = $version->letterNumber->only(['page_number', 'sequence_number']);

        $this->post($this->url($version, 'publish'), $this->payload($version, publish: true))->assertSessionHasNoErrors()->assertRedirectToRoute('lt.edit', ['letterTemplate' => $version->template->uuid, 'version' => $version->uuid]);

        $this->assertSame(LetterTemplateVersionStatusEnum::PUBLISHED, $version->fresh()->status);
        $this->assertNotNull($version->fresh()->published_at);
        $this->assertSame($version->id, $version->template->fresh()->active_version_id);
        $this->assertSame(LetterTemplateVersionStatusEnum::PUBLISHED, $history->fresh()->status);
        $this->assertSame('historical.docx', $history->fresh()->source_path);
        $this->assertSame($source, Storage::disk('local')->get($version->source_path));
        $this->assertSame($result, Storage::disk('local')->get($version->test_path));
        $this->assertSame($counters, $version->letterNumber->fresh()->only(['page_number', 'sequence_number']));
        $this->assertDatabaseHas('letter_template_audits', ['version_id' => $version->id, 'operation' => 'version.published']);
    }

    #[DataProvider('publicationFailures')]
    public function test_publication_rechecks_every_required_piece_of_evidence(string $failure): void
    {
        $version = $this->ready(withConfiguration: true);
        match ($failure) {
            'validation' => $version->update(['validated_at' => null]),
            'configuration' => $version->variables->first()->update(['default_text' => 'Berubah']),
            'source missing' => Storage::disk('local')->delete($version->source_path),
            'source changed' => Storage::disk('local')->put($version->source_path, 'Sumber berubah'),
            'result missing' => Storage::disk('local')->delete($version->test_path),
            'result changed' => Storage::disk('local')->put($version->test_path, 'Hasil berubah'),
            'expired' => $version->update(['test_expires_at' => now()]),
            'unconfirmed' => $version->update(['visually_confirmed_at' => null]),
            'legacy confirmation' => $version->update(['visually_confirmed_by_uuid' => null]),
            'inactive candidate' => $version->signerSlots->sole()->candidates->sole()->employee->update(['status' => AsnStatusEnum::PENSIUN]),
        };
        $version->refresh();
        $audits = LetterTemplateAudit::query()->count();

        $this->post($this->url($version, 'publish'), $this->payload($version, publish: true))->assertInvalid('publication');

        $this->assertSame(LetterTemplateVersionStatusEnum::DRAFT, $version->fresh()->status);
        $this->assertNull($version->template->fresh()->active_version_id);
        $this->assertNull($version->fresh()->published_at);
        $this->assertDatabaseCount('letter_template_audits', $audits);
    }

    public static function publicationFailures(): array
    {
        return array_map(fn(string $failure): array => [$failure], ['validation', 'configuration', 'source missing', 'source changed', 'result missing', 'result changed', 'expired', 'unconfirmed', 'legacy confirmation', 'inactive candidate']);
    }

    public function test_publication_checks_unused_signer_slots_but_does_not_require_signer_tokens(): void
    {
        $version = $this->ready(withConfiguration: true);
        $this->assertSame([], $version->test_signer_uuids);
        $this->assertSame(['tujuan'], $version->validation_result['placeholders']);

        $this->post($this->url($version, 'publish'), $this->payload($version, publish: true))->assertSessionHasNoErrors();

        $this->assertSame(LetterTemplateVersionStatusEnum::PUBLISHED, $version->fresh()->status);
    }

    public function test_stale_version_or_result_tokens_cannot_publish_and_confirmation_is_required(): void
    {
        $version = $this->ready();
        $old = $this->payload($version, publish: true);
        $this->post($this->url($version, 'publish'), [...$old, 'confirmed' => false])->assertInvalid('confirmed');
        $variable = $version->variables->sole();
        $this->post(route('lt.test.store', [$version->template->uuid, $version->uuid]), ['validation_token' => app(LetterTemplateTestEvidence::class)->validationToken($version), 'values' => [$variable->uuid => 'Jakarta'], 'signers' => []])->assertSessionHasNoErrors();
        $version->refresh();
        $this->post(route('lt.test.confirm', [$version->template->uuid, $version->uuid]), ['test_token' => app(LetterTemplateTestEvidence::class)->token($version), 'confirmed' => true])->assertSessionHasNoErrors();
        $version->refresh();

        $this->post($this->url($version, 'publish'), $old)->assertInvalid('version');
        $this->post($this->url($version, 'publish'), [...$this->payload($version, publish: true), 'test_token' => $old['test_token']])->assertInvalid('test');
        $this->assertSame(LetterTemplateVersionStatusEnum::DRAFT, $version->fresh()->status);
    }

    public function test_reopening_unused_publication_keeps_uuid_number_configuration_and_source_but_revokes_all_evidence(): void
    {
        $version = $this->ready(withConfiguration: true);
        $this->publish($version);
        $variables = $version->variables->pluck('uuid')->all();
        $path = $version->source_path;
        $source = Storage::disk('local')->get($path);
        $files = Storage::disk('local')->allFiles();

        $this->post($this->url($version, 'draft'), $this->payload($version))->assertSessionHasNoErrors()->assertRedirectToRoute('lt.edit', ['letterTemplate' => $version->template->uuid, 'version' => $version->uuid]);

        $this->assertSame(LetterTemplateVersionStatusEnum::DRAFT, $version->fresh()->status);
        $this->assertSame(1, $version->fresh()->version_number);
        $this->assertNull($version->fresh()->published_at);
        $this->assertNull($version->template->fresh()->active_version_id);
        $this->assertSame($variables, $version->fresh()->variables->pluck('uuid')->all());
        $this->assertSame($source, Storage::disk('local')->get($path));
        $this->assertSame($files, Storage::disk('local')->allFiles());
        foreach ($this->evidenceFields() as $field) {
            $this->assertNull($version->fresh()->getAttribute($field), $field);
        }
        $this->assertDatabaseCount('letter_template_versions', 1);
        $this->post($this->url($version, 'publish'), $this->payload($version, publish: true))->assertInvalid('publication');
        $this->assertDatabaseHas('letter_template_audits', ['version_id' => $version->id, 'operation' => 'draft.reopened']);
    }

    public function test_reopening_an_older_unused_version_preserves_another_active_publication(): void
    {
        $version = $this->ready();
        $this->publish($version);
        $current = LetterTemplateVersion::factory()->for($version->template, 'template')->create(['version_number' => 2, 'status' => LetterTemplateVersionStatusEnum::PUBLISHED, 'first_used_at' => now()]);
        $version->template->update(['active_version_id' => $current->id]);

        $this->post($this->url($version, 'draft'), $this->payload($version))->assertSessionHasNoErrors();

        $this->assertSame(LetterTemplateVersionStatusEnum::DRAFT, $version->fresh()->status);
        $this->assertSame($current->id, $version->template->fresh()->active_version_id);
        $this->assertSame(LetterTemplateVersionStatusEnum::PUBLISHED, $current->fresh()->status);
    }

    public function test_first_usage_after_opening_rejects_a_stale_reopen_request_and_then_requires_a_copy(): void
    {
        $version = $this->ready();
        $this->publish($version);
        $old = $this->payload($version);
        $version->update(['first_used_at' => now()]);

        $this->post($this->url($version, 'draft'), $old)->assertInvalid('version');
        $this->assertSame(LetterTemplateVersionStatusEnum::PUBLISHED, $version->fresh()->status);
        $this->assertDatabaseCount('letter_template_versions', 1);
        $this->post($this->url($version, 'draft'), $this->payload($version))->assertSessionHasNoErrors();
        $this->assertSame(2, $version->template->fresh()->draftVersion->version_number);
    }

    public function test_failed_publication_rolls_back_draft_status_and_previous_active_pointer(): void
    {
        $version = $this->ready(versionNumber: 2);
        $previous = LetterTemplateVersion::factory()->for($version->template, 'template')->create(['version_number' => 1, 'status' => LetterTemplateVersionStatusEnum::PUBLISHED]);
        $version->template->update(['active_version_id' => $previous->id]);
        $audits = LetterTemplateAudit::query()->count();
        Exceptions::fake();
        LetterTemplateAudit::creating(function (): void { throw new RuntimeException('Simulasi gagal audit publikasi.'); });
        try {
            $this->post($this->url($version, 'publish'), $this->payload($version, publish: true))->assertInternalServerError();
        } finally {
            LetterTemplateAudit::getEventDispatcher()->forget('eloquent.creating: ' . LetterTemplateAudit::class);
        }

        Exceptions::assertReported(RuntimeException::class);
        $this->assertSame(LetterTemplateVersionStatusEnum::DRAFT, $version->fresh()->status);
        $this->assertNull($version->fresh()->published_at);
        $this->assertSame($previous->id, $version->template->fresh()->active_version_id);
        $this->assertDatabaseCount('letter_template_audits', $audits);
    }

    public function test_used_publication_is_copied_with_independent_children_and_file_without_test_evidence(): void
    {
        $version = $this->ready(withConfiguration: true);
        $this->publish($version);
        $version->update(['first_used_at' => now()]);
        $original = $version->fresh()->getAttributes();
        $source = Storage::disk('local')->get($version->source_path);
        $test = Storage::disk('local')->get($version->test_path);
        $oldVariables = $version->variables->pluck('uuid')->all();
        $oldSlot = $version->signerSlots->sole();

        $this->post($this->url($version, 'draft'), $this->payload($version))->assertSessionHasNoErrors();

        $draft = $version->template->fresh()->draftVersion;
        $this->assertSame(2, $draft->version_number);
        $this->assertNotSame($version->uuid, $draft->uuid);
        $this->assertSame($original, $version->fresh()->getAttributes());
        $this->assertSame($version->id, $version->template->fresh()->active_version_id);
        $this->assertSame($version->letter_number_id, $draft->letter_number_id);
        $this->assertSame([], array_intersect($oldVariables, $draft->variables->pluck('uuid')->all()));
        $this->assertNotSame($oldSlot->uuid, $draft->signerSlots->sole()->uuid);
        $this->assertSame($oldSlot->candidates->pluck('employee_id')->all(), $draft->signerSlots->sole()->candidates->pluck('employee_id')->all());
        $this->assertSame('Fallback tujuan', $draft->variables->first()->default_text);
        $employee = $draft->variables->firstWhere('name', 'pegawai');
        $this->assertSame('NIP belum tersedia', $employee->employeeAttributes->firstWhere('source_attribute', 'nip')->default_text);
        $this->assertSame('d F Y', $employee->employeeAttributes->firstWhere('source_attribute', 'join_date')->date_format);
        $this->assertSame('Y/m/d', $draft->variables->firstWhere('name', 'daftar')->columns->sole()->date_format);
        $this->assertSame('NIP penandatangan', $draft->signerSlots->sole()->employeeAttributes->sole()->default_text);
        $this->assertNotSame($version->source_path, $draft->source_path);
        $this->assertSame($source, Storage::disk('local')->get($draft->source_path));
        $this->assertSame($source, Storage::disk('local')->get($version->source_path));
        $this->assertSame($test, Storage::disk('local')->get($version->test_path));
        foreach ([...$this->evidenceFields(), 'published_at', 'first_used_at'] as $field) {
            $this->assertNull($draft->getAttribute($field), $field);
        }
        $this->patch(route('lt.configuration.variables.attribute', [$version->template->uuid, $draft->uuid, $employee->uuid, 'nip']), ['default_text' => 'Fallback salinan', 'date_format' => null])->assertSessionHasNoErrors();
        $this->assertSame('NIP belum tersedia', $version->fresh()->variables->firstWhere('name', 'pegawai')->employeeAttributes->firstWhere('source_attribute', 'nip')->default_text);
        $this->assertStringContainsString('NIP belum tersedia', $this->guideText($version));
        $this->assertStringNotContainsString('Fallback salinan', $this->guideText($version));
        $this->assertStringContainsString('Fallback salinan', $this->guideText($draft));
        $this->withoutVite()->get(route('lt.edit', ['letterTemplate' => $version->template->uuid, 'version' => $draft->uuid]))->assertOk()->assertInertia(fn(Assert $page) => $page->where('configuration.data.has_configuration', true));
        $this->assertDatabaseHas('letter_template_audits', ['version_id' => $draft->id, 'operation' => 'draft.copied']);
    }

    public function test_replacing_copied_source_keeps_the_published_source_available(): void
    {
        $version = $this->ready();
        $this->publish($version);
        $version->update(['first_used_at' => now()]);
        $original = Storage::disk('local')->get($version->source_path);
        $this->post($this->url($version, 'draft'), $this->payload($version))->assertSessionHasNoErrors();
        $draft = $version->template->fresh()->draftVersion;
        $copied = $draft->source_path;

        $this->upload($draft, 'Dokumen salinan baru');

        Storage::disk('local')->assertMissing($copied);
        $this->assertSame($original, Storage::disk('local')->get($version->source_path));
        $this->get(route('lt.source.download', [$version->template->uuid, $version->uuid]))->assertOk();
    }

    public function test_archiving_preserves_records_and_files_and_allows_a_new_numbered_copy(): void
    {
        $version = $this->ready(withConfiguration: true);
        $variables = $version->variables->modelKeys();
        $files = Storage::disk('local')->allFiles();
        $testPath = $version->test_path;

        $this->post($this->url($version, 'archive'), $this->payload($version))->assertSessionHasNoErrors();

        $this->assertSame(LetterTemplateVersionStatusEnum::ARCHIVED, $version->fresh()->status);
        $this->assertNull($version->template->fresh()->draftVersion);
        $this->assertSame($variables, $version->fresh()->variables->modelKeys());
        $this->assertSame($files, Storage::disk('local')->allFiles());
        $this->assertSame($testPath, $version->fresh()->test_path);
        $this->assertDatabaseCount('archives', 0);
        $this->assertDatabaseCount('archive_records', 0);
        $this->post($this->url($version, 'draft'), $this->payload($version))->assertSessionHasNoErrors();
        $this->assertSame(2, $version->template->fresh()->draftVersion->version_number);
        $this->assertSame(LetterTemplateVersionStatusEnum::ARCHIVED, $version->fresh()->status);
    }

    public function test_existing_draft_blocks_reopen_or_copy_and_source_remains_unchanged(): void
    {
        $version = $this->ready();
        $this->publish($version);
        $other = LetterTemplateVersion::factory()->for($version->template, 'template')->create(['version_number' => 2]);
        $state = $version->fresh()->getAttributes();

        $this->post($this->url($version, 'draft'), $this->payload($version))->assertInvalid('version');

        $this->assertSame($state, $version->fresh()->getAttributes());
        $this->assertSame($other->id, $version->template->fresh()->draftVersion->id);
        $this->assertSame($version->id, $version->template->fresh()->active_version_id);
        $this->assertDatabaseCount('letter_template_versions', 2);
    }

    #[DataProvider('unarchivableStates')]
    public function test_only_unused_drafts_can_be_archived(LetterTemplateVersionStatusEnum $status, bool $used): void
    {
        $version = $this->ready();
        $version->update(['status' => $status, 'first_used_at' => $used ? now() : null]);

        $this->post($this->url($version, 'archive'), $this->payload($version))->assertInvalid('version');

        $this->assertSame($status, $version->fresh()->status);
    }

    public static function unarchivableStates(): array
    {
        return [[LetterTemplateVersionStatusEnum::PUBLISHED, false], [LetterTemplateVersionStatusEnum::ARCHIVED, false], [LetterTemplateVersionStatusEnum::DRAFT, true]];
    }

    public function test_activation_is_explicit_idempotent_and_does_not_change_pointer_or_evidence(): void
    {
        $version = $this->ready();
        $this->publish($version);
        $original = $version->fresh()->getAttributes();
        $url = route('lt.activation.update', [$version->template->uuid, $version->uuid]);

        $this->patch($url, ['is_active' => false, 'confirmed' => true, 'active_version_id' => null])->assertSessionHasNoErrors();
        $this->patch($url, ['is_active' => false, 'confirmed' => true])->assertSessionHasNoErrors();

        $this->assertFalse($version->template->fresh()->is_active);
        $this->assertSame($version->id, $version->template->fresh()->active_version_id);
        $this->assertSame($original, $version->fresh()->getAttributes());
        $this->assertSame(1, LetterTemplateAudit::query()->where('operation', 'template.deactivated')->count());
        $this->patch($url, ['is_active' => true, 'confirmed' => false])->assertInvalid('confirmed');
        $this->assertFalse($version->template->fresh()->is_active);
        $this->patch($url, ['is_active' => true, 'confirmed' => true])->assertSessionHasNoErrors();
        $this->assertTrue($version->template->fresh()->is_active);
    }

    public function test_publication_does_not_reactivate_an_inactive_template(): void
    {
        $version = $this->ready();
        $version->template->update(['is_active' => false]);

        $this->post($this->url($version, 'publish'), $this->payload($version, publish: true))->assertSessionHasNoErrors();

        $this->assertFalse($version->template->fresh()->is_active);
        $this->assertSame($version->id, $version->template->fresh()->active_version_id);
    }

    public function test_version_selection_is_scoped_and_timeline_exposes_safe_read_only_history(): void
    {
        $version = $this->ready(withConfiguration: true);
        $this->publish($version);
        $version->update(['first_used_at' => now()]);
        $this->post($this->url($version, 'draft'), $this->payload($version))->assertSessionHasNoErrors();
        $draft = $version->template->fresh()->draftVersion;

        $this->withoutVite()->get(route('lt.edit', ['letterTemplate' => $version->template->uuid, 'version' => $version->uuid]))->assertOk()->assertInertia(fn(Assert $page) => $page
            ->where('lifecycle.data.id', $version->uuid)->where('configuration.data.editable', false)
            ->where('lifecycle.data.timezone', 'Asia/Makassar')
            ->where('lifecycle.data.can_start_draft', false)->where('lifecycle.data.draft.id', $draft->uuid)
            ->where('lifecycle.data.is_current', true)->where('lifecycle.data.versions.0.id', $draft->uuid)
            ->has('lifecycle.data.versions', 2)->has('lifecycle.data.audits')
            ->missing('lifecycle.data.source_path')->missing('lifecycle.data.letter_template_id'));
        $this->get(route('lt.edit', $version->template->uuid))->assertOk()->assertInertia(fn(Assert $page) => $page->where('lifecycle.data.id', $draft->uuid)->where('configuration.data.editable', true));
        $foreign = LetterTemplateVersion::factory()->create();
        $this->get(route('lt.edit', ['letterTemplate' => $version->template->uuid, 'version' => $foreign->uuid]))->assertNotFound();
        $this->get(route('lt.edit', ['letterTemplate' => $version->template->uuid, 'version' => 'invalid']))->assertInvalid('version');
    }

    public function test_stale_archive_request_preserves_new_configuration_and_audits(): void
    {
        $version = $this->ready();
        $payload = $this->payload($version);
        $variable = $version->variables->sole();
        $variable->update(['default_text' => 'Isian yang baru disimpan']);
        $audits = LetterTemplateAudit::query()->count();

        $this->post($this->url($version, 'archive'), $payload)->assertInvalid('version');

        $this->assertSame(LetterTemplateVersionStatusEnum::DRAFT, $version->fresh()->status);
        $this->assertSame('Isian yang baru disimpan', $variable->fresh()->default_text);
        $this->assertDatabaseCount('letter_template_audits', $audits);
    }

    public function test_failed_copy_cleans_new_files_and_rolls_back_version_configuration_and_audit(): void
    {
        $version = $this->ready(withConfiguration: true);
        $this->publish($version);
        $version->update(['first_used_at' => now()]);
        $files = Storage::disk('local')->allFiles();
        $audits = LetterTemplateAudit::query()->count();
        Exceptions::fake();
        LetterTemplateAudit::creating(function (): void { throw new RuntimeException('Simulasi gagal audit salinan.'); });
        try {
            $this->post($this->url($version, 'draft'), $this->payload($version))->assertInternalServerError();
        } finally {
            LetterTemplateAudit::getEventDispatcher()->forget('eloquent.creating: ' . LetterTemplateAudit::class);
        }

        Exceptions::assertReported(RuntimeException::class);
        $this->assertSame($files, Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('letter_template_versions', 1);
        $this->assertDatabaseCount('letter_template_variables', 3);
        $this->assertDatabaseCount('letter_template_audits', $audits);
        $this->assertSame($version->id, $version->template->fresh()->active_version_id);
    }

    public function test_copy_failure_and_missing_source_do_not_leave_a_draft_or_touch_the_original(): void
    {
        $version = $this->ready();
        $this->publish($version);
        $version->update(['first_used_at' => now()]);
        $disk = Storage::disk('local');
        $files = $disk->allFiles();
        $proxy = Mockery::mock($disk);
        $proxy->shouldReceive('copy')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($proxy);

        $this->post($this->url($version, 'draft'), $this->payload($version))->assertInvalid('version');

        $this->assertSame($files, $disk->allFiles());
        $this->assertDatabaseCount('letter_template_versions', 1);
        $disk->delete($version->source_path);
        $this->post($this->url($version, 'draft'), $this->payload($version))->assertInvalid('version');
        $this->assertDatabaseCount('letter_template_versions', 1);
    }

    #[DataProvider('operations')]
    public function test_operation_routes_require_login_and_scope_the_version_to_its_parent(string $operation): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $foreign = LetterTemplateVersion::factory()->create();
        $payload = ['version_token' => str_repeat('a', 64), 'test_token' => str_repeat('b', 64), 'confirmed' => true, 'is_active' => false];
        $method = $operation === 'activation' ? 'patch' : 'post';
        $this->$method($this->url($version, $operation), $payload)->assertRedirectToRoute('login.index');
        $this->actingAs(UserFactory::new()->create());
        foreach ([$foreign->uuid, (string) Str::uuid(), (string) $version->id] as $identifier) {
            $route = $operation === 'activation' ? 'lt.activation.update' : 'lt.versions.' . $operation;
            $this->$method(route($route, [$version->template->uuid, $identifier]), $payload)->assertNotFound();
        }
        $this->assertDatabaseCount('letter_template_audits', 0);
    }

    public static function operations(): array
    {
        return [['publish'], ['draft'], ['archive'], ['activation']];
    }

    private function ready(bool $withConfiguration = false, int $versionNumber = 1): LetterTemplateVersion
    {
        Storage::fake('local');
        $this->freezeTime();
        $this->actingAs(UserFactory::new()->create());
        $version = LetterTemplateVersion::factory()->create(['version_number' => $versionNumber]);
        $variable = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'tujuan', 'default_text' => 'Fallback tujuan']);
        if ($withConfiguration) {
            $employee = Employee::factory()->create(['status' => AsnStatusEnum::AKTIF]);
            $person = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'pegawai', 'sort_order' => 1, 'type' => 4, 'option_source' => 2]);
            $person->employeeAttributes()->createMany([['source_attribute' => 'nip', 'default_text' => 'NIP belum tersedia'], ['source_attribute' => 'join_date', 'date_format' => 'd F Y']]);
            $multiple = LetterTemplateVariable::factory()->for($version, 'version')->create(['name' => 'daftar', 'sort_order' => 2, 'type' => 4, 'option_source' => 2, 'is_multiple' => true]);
            LetterTemplateVariableColumn::factory()->for($multiple, 'variable')->create(['source_attribute' => 'join_date', 'placeholder' => 'kolom_tanggal', 'date_format' => 'Y/m/d']);
            $slot = LetterTemplateSignerSlot::factory()->for($version, 'version')->create();
            $slot->candidates()->create(['employee_id' => $employee->id]);
            $slot->employeeAttributes()->create(['source_attribute' => 'nip', 'default_text' => 'NIP penandatangan']);
        }
        $this->upload($version, '${tujuan}');
        $this->post(route('lt.validation.store', [$version->template->uuid, $version->uuid]))->assertSessionHasNoErrors();
        $version->refresh();
        $this->post(route('lt.test.store', [$version->template->uuid, $version->uuid]), ['validation_token' => app(LetterTemplateTestEvidence::class)->validationToken($version), 'values' => [$variable->uuid => 'Jakarta'], 'signers' => []])->assertSessionHasNoErrors();
        $version->refresh();
        $this->post(route('lt.test.confirm', [$version->template->uuid, $version->uuid]), ['test_token' => app(LetterTemplateTestEvidence::class)->token($version), 'confirmed' => true])->assertSessionHasNoErrors();

        return $version->fresh();
    }

    private function guideText(LetterTemplateVersion $version): string
    {
        $response = $this->get(route('lt.example.download', [$version->template->uuid, $version->uuid]))->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            return app(InspectLetterTemplateDocx::class)->read($path)['word/document.xml']->textContent;
        } finally {
            unlink($path);
        }
    }

    private function upload(LetterTemplateVersion $version, string $text): void
    {
        $path = tempnam(sys_get_temp_dir(), 'letter-version-test-');
        try {
            $word = new PhpWord();
            $word->addSection()->addText($text);
            IOFactory::createWriter($word, 'Word2007')->save($path);
            $this->post(route('lt.source.store', [$version->template->uuid, $version->uuid]), ['docx' => UploadedFile::fake()->createWithContent('source.docx', file_get_contents($path))])->assertSessionHasNoErrors();
        } finally {
            unlink($path);
        }
    }

    private function publish(LetterTemplateVersion $version): void
    {
        $this->post($this->url($version, 'publish'), $this->payload($version, publish: true))->assertSessionHasNoErrors();
        $version->refresh();
    }

    private function url(LetterTemplateVersion $version, string $operation): string
    {
        return route($operation === 'activation' ? 'lt.activation.update' : 'lt.versions.' . $operation, [$version->template->uuid, $version->uuid]);
    }

    private function payload(LetterTemplateVersion $version, bool $publish = false): array
    {
        $version = $version->fresh();

        return ['confirmed' => true, 'version_token' => app(LetterTemplateVersionState::class)->token($version), ...($publish ? ['test_token' => app(LetterTemplateTestEvidence::class)->token($version) ?? str_repeat('0', 64)] : [])];
    }

    private function evidenceFields(): array
    {
        return ['validated_source_sha256', 'validated_configuration_sha256', 'validated_at', 'validation_result', 'tested_source_sha256', 'tested_configuration_sha256', 'test_disk', 'test_path', 'test_sha256', 'test_signer_uuids', 'tested_at', 'test_expires_at', 'visually_confirmed_at', 'visually_confirmed_by', 'visually_confirmed_by_uuid', 'visually_confirmed_by_name'];
    }
}
