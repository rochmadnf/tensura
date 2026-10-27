<?php

namespace Tests\Feature;

use App\Enums\LetterOperationStatusEnum as OperationStatus;
use App\Enums\LetterStatusEnum;
use App\Enums\LetterTemplateVersionStatusEnum;
use App\Jobs\GenerateLetterPreview;
use App\Jobs\IssueLetter;
use App\Models\Letter;
use App\Models\LetterArtifact;
use App\Models\LetterOperation;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\Letter\LetterPdfState;
use App\Services\Letter\ManageLetterLifecycle;
use App\Services\Letter\RunLetterIssuance;
use App\Services\Letter\RunLetterPreview;
use Carbon\CarbonImmutable;
use Database\Factories\LetterNumberFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\CreatesPdfArtifact;
use Tests\TestCase;

class LetterLifecycleTest extends TestCase
{
    use CreatesPdfArtifact;
    use LazilyRefreshDatabase;

    public function test_revision_is_shared_keeps_official_identity_and_appends_a_version_without_a_new_claim(): void
    {
        $letter = $this->draft();
        $this->publish($letter);
        $letter = $letter->fresh();
        $first = $letter->currentVersion;
        $original = Storage::disk('local')->get($first->wordArtifact->path);
        $this->action($letter, 'revise');
        $draft = $letter->fresh()->draft;
        $this->action($letter->fresh(), 'revise');
        $this->assertSame($draft->uuid, $letter->fresh()->draft->uuid);
        $this->assertSame(2, $draft->kind->value);
        $this->assertSame($first->id, $draft->base_version_id);
        $this->save($letter->fresh(), ['subject' => 'Perihal revisi', 'reason' => 'Perbaikan isi']);
        $this->assertSame('Surat awal', $letter->fresh()->subject);
        $this->publish($letter->fresh());
        $letter = $letter->fresh();
        $this->assertSame(2, $letter->currentVersion->version_number);
        $this->assertSame('Perihal revisi', $letter->subject);
        $this->assertSame($first->number, $letter->official_number);
        $this->assertSame($first->letter_number_assignment_id, $letter->currentVersion->letter_number_assignment_id);
        $this->assertSame('Perbaikan isi', $letter->currentVersion->reason);
        $this->assertSame($original, Storage::disk('local')->get($first->wordArtifact->path));
        $this->assertDatabaseCount('letter_number_assignments', 1);
        $this->assertSame(1, $letter->currentVersion->assignment->period->last_sequence_number);
        $this->get(route('ltr.show', $letter->uuid))->assertInertia(fn(AssertableInertia $page) => $page->missing('resource.data.draft')->has('versions.data', 2));
    }

    public function test_revision_needs_reason_and_cannot_change_date_or_number_and_old_editor_cannot_edit_recreated_draft(): void
    {
        $letter = $this->draft();
        $this->publish($letter);
        $letter = $letter->fresh();
        $this->action($letter, 'revise');
        $letter = $letter->fresh();
        $draft = $letter->draft;
        $this->save($letter, ['letter_date' => '2026-10-25'])->assertSessionHasErrors('draft');
        $this->save($letter, ['page_number' => 9])->assertSessionHasErrors('draft');
        $this->publish($letter, expectReasonFailure: true);
        $this->action($letter->fresh(), 'discardDraft');
        $this->action($letter->fresh(), 'revise');
        $new = $letter->fresh();
        $this->assertNotSame($draft->uuid, $new->draft->uuid);
        $this->assertGreaterThan($draft->revision, $new->draft->revision);
        $this->save($new, ['draft_id' => $draft->uuid, 'base_version' => $draft->baseVersion?->uuid, 'revision' => $draft->revision, 'draft_id' => $draft->uuid, 'subject' => 'Stale'])->assertSessionHasErrors('revision');
        $this->assertNotSame('Stale', $new->fresh()->draft->subject);
        $this->delete(route('ltr.destroy', $new->uuid), ['revision' => $new->draft->revision, 'confirmed' => true])->assertSessionHasErrors('draft');
    }

    public function test_cancellation_discards_pending_revision_releases_number_and_preserves_official_word_and_audit(): void
    {
        $letter = $this->draft();
        $this->publish($letter);
        $letter = $letter->fresh();
        $version = $letter->currentVersion;
        $this->action($letter, 'revise');
        $letter = $letter->fresh();
        $uuid = $this->postJson(route('ltr.previews.store', $letter->uuid), ['draft_id' => $letter->draft->uuid, 'base_version' => $letter->draft->baseVersion?->uuid, 'revision' => $letter->draft->revision, 'idempotency_key' => (string) Str::uuid()])->assertAccepted()->json('operation.id');
        $this->action($letter, 'cancel', ['reason' => ''])->assertSessionHasErrors('reason');
        $this->action($letter, 'cancel', ['reason' => 'Tidak berlaku']);
        $this->assertSame(LetterStatusEnum::CANCELLED, $letter->fresh()->status);
        $this->assertNull($letter->fresh()->draft);
        $this->assertNotNull($version->assignment->fresh()->released_at);
        $this->assertSame(OperationStatus::STALE, LetterOperation::query()->where('uuid', $uuid)->sole()->status);
        app(RunLetterPreview::class)->run($uuid);
        $this->assertSame(1, $letter->versions()->count());
        $this->get(route('ltr.versions.file', [$letter->uuid, $version->uuid]))->assertUnprocessable();
        $this->assertSame(1, $letter->audits()->where('event', 'letter.cancelled')->count());
        $this->assertSame('Tidak berlaku', $letter->fresh()->cancellation_reason);
    }

    public function test_recovery_reuses_available_number_in_original_period_and_preserves_prior_versions(): void
    {
        $letter = $this->draft();
        $this->publish($letter);
        $letter = $letter->fresh();
        $old = $letter->currentVersion;
        $this->action($letter, 'cancel', ['reason' => 'Kesalahan']);
        $this->action($letter->fresh(), 'recover');
        $letter = $letter->fresh();
        $this->save($letter, ['reason' => 'Sudah diperbaiki']);
        $this->assertSame(LetterStatusEnum::CANCELLED, $letter->fresh()->status);
        $this->publish($letter->fresh());
        $letter = $letter->fresh();
        $this->assertSame(LetterStatusEnum::PUBLISHED, $letter->status);
        $this->assertSame($old->number, $letter->official_number);
        $this->assertSame('2026-10-26', $letter->official_date->toDateString());
        $this->assertSame(3, $letter->currentVersion->assignment->mode);
        $this->assertSame(2, $letter->versions()->count());
        $this->assertNull($letter->cancelled_at);
        $this->assertSame(1, $letter->currentVersion->assignment->period->last_sequence_number);
    }

    public function test_reused_number_points_to_current_owner_and_recovery_requires_an_explicit_free_number(): void
    {
        $letter = $this->draft();
        $this->publish($letter);
        $letter = $letter->fresh();
        $this->action($letter, 'cancel', ['reason' => 'Batal A']);
        $other = $this->draft(reset: false, template: $letter->template->activeVersion);
        $this->save($other, ['numbering_mode' => 2, 'page_number' => 1, 'sequence_number' => 1]);
        $this->publish($other->fresh());
        $detail = app(ManageLetterLifecycle::class)->detail($letter->fresh());
        $this->assertSame($other->uuid, $detail['number_owner']['id']);
        $this->action($letter->fresh(), 'recover');
        $this->save($letter->fresh(), ['reason' => 'Pulih']);
        $this->publish($letter->fresh(), expectNumberFailure: true);
        $this->save($letter->fresh(), ['reason' => 'Pulih dengan pengganti', 'sequence_number' => 2]);
        $this->publish($letter->fresh());
        $this->assertSame('445/01.02/PKM-KMJ/X/2026', $letter->fresh()->official_number);
        $this->assertSame('445/01.01/PKM-KMJ/X/2026', $other->fresh()->official_number);
        $this->assertSame(1, $letter->fresh()->currentVersion->assignment->period->last_sequence_number);
    }

    public function test_revision_uses_latest_template_but_keeps_original_classification_number_and_date(): void
    {
        $letter = $this->draft();
        $this->publish($letter);
        $letter = $letter->fresh();
        $base = $letter->currentVersion;
        $old = $base->templateVersion;
        $classification = LetterNumberFactory::new()->create(['classification_code' => '446', 'page_number' => null, 'sequence_number' => null]);
        $next = LetterTemplateVersion::factory()->create(['letter_template_id' => $letter->letter_template_id, 'version_number' => 2, 'letter_number_id' => $classification->id, 'status' => LetterTemplateVersionStatusEnum::PUBLISHED, 'source_disk' => $old->source_disk, 'source_path' => $old->source_path, 'source_sha256' => $old->source_sha256, 'validation_result' => $old->validation_result]);
        $letter->template->update(['active_version_id' => $next->id]);
        $this->action($letter->fresh(), 'revise');
        $letter = $letter->fresh();
        $this->assertSame($next->id, $letter->draft->template_version_id);
        $this->save($letter, ['reason' => 'Mengikuti templat terbaru'])->assertSessionHasNoErrors();
        $this->publish($letter->fresh());
        $this->assertSame($base->number, $letter->fresh()->official_number);
        $this->assertSame($next->id, $letter->fresh()->currentVersion->template_version_id);
        $this->assertSame($base->letter_number_assignment_id, $letter->fresh()->currentVersion->letter_number_assignment_id);
    }

    public function test_cancelled_download_requires_status_confirmation_and_reading_history_creates_no_audit(): void
    {
        $letter = $this->draft();
        $this->publish($letter);
        $letter = $letter->fresh();
        $version = $letter->currentVersion;
        $this->action($letter, 'cancel', ['reason' => 'Batal untuk arsip']);
        $count = $letter->audits()->count();
        $this->get(route('ltr.versions.download', [$letter->uuid, $version->uuid]))->assertUnprocessable();
        $token = app(ManageLetterLifecycle::class)->token($letter->fresh());
        $this->get(route('ltr.versions.download', [$letter->uuid, $version->uuid, 'confirmation' => $token]))->assertOk()->assertDownload();
        $this->get(route('ltr.show', $letter->uuid))->assertOk();
        $this->assertSame($count, $letter->audits()->count());
        auth()->logout();
        $this->post(route('ltr.recover', $letter->uuid), [])->assertRedirectToRoute('login.index');
    }

    public function test_cancellation_rejects_a_stale_state_and_releasing_the_reused_number_clears_its_current_owner(): void
    {
        $letter = $this->draft();
        $this->publish($letter);
        $letter = $letter->fresh();
        $stale = app(ManageLetterLifecycle::class)->detail($letter);
        $this->action($letter, 'revise');
        $this->post(route('ltr.cancel', $letter->uuid), ['state_token' => $stale['state_token'], 'base_version' => $stale['base_version'], 'reason' => 'Old modal', 'confirmed' => true])->assertSessionHasErrors('lifecycle');
        $this->action($letter->fresh(), 'cancel', ['reason' => 'Batal A']);
        $other = $this->draft(reset: false, template: $letter->template->activeVersion);
        $this->save($other, ['numbering_mode' => 2, 'page_number' => 1, 'sequence_number' => 1]);
        $this->publish($other->fresh());
        $this->assertSame($other->uuid, app(ManageLetterLifecycle::class)->detail($letter->fresh())['number_owner']['id']);
        $this->action($other->fresh(), 'cancel', ['reason' => 'Batal B']);
        $this->assertNull(app(ManageLetterLifecycle::class)->detail($letter->fresh())['number_owner']);
        $this->assertSame(1, $letter->audits()->where('event', 'number.reused')->count());
        $this->assertSame(1, $letter->audits()->where('event', 'number.available')->count());
    }

    private function draft(bool $reset = true, ?LetterTemplateVersion $template = null): Letter
    {
        if ($reset) {
            Storage::fake('local');
            Bus::fake([GenerateLetterPreview::class, IssueLetter::class]);
            $this->travelTo(CarbonImmutable::parse('2026-10-26 12:00:00'));
            $this->actingAs(UserFactory::new()->create());
        }
        $version = $template ?? LetterTemplateVersion::factory()->create(['status' => LetterTemplateVersionStatusEnum::PUBLISHED, 'validation_result' => ['valid' => true, 'placeholders' => ['nomor_surat', 'tanggal_surat']]]);
        $version->letterNumber->update(['classification_code' => '445', 'page_number' => null, 'sequence_number' => null]);
        $version->template->update(['is_active' => true, 'active_version_id' => $version->id]);
        if ($template === null) {
            $disk = Storage::disk('local');
            $disk->makeDirectory('tests');
            $doc = new PhpWord();
            $doc->addSection()->addText('Surat ${nomor_surat} ${tanggal_surat}');
            IOFactory::createWriter($doc, 'Word2007')->save($disk->path('tests/source.docx'));
            $version->update(['source_disk' => 'local', 'source_path' => 'tests/source.docx', 'source_sha256' => hash_file('sha256', $disk->path('tests/source.docx'))]);
        }
        $this->post(route('ltr.store'), ['template' => $version->template->uuid, 'template_version' => $version->uuid, 'subject' => 'Surat awal', 'letter_date' => '2026-10-26', 'numbering_mode' => 1, 'number_year' => 2026, 'number_month' => 10, 'values' => [], 'signers' => []])->assertSessionHasNoErrors();

        return Letter::query()->latest('id')->firstOrFail();
    }

    private function action(Letter $letter, string $action, array $extra = []): TestResponse
    {
        $data = app(ManageLetterLifecycle::class)->detail($letter);
        $response = $this->post(route('ltr.' . $action, $letter->uuid), ['state_token' => $data['state_token'], 'base_version' => $data['base_version'], 'confirmed' => true, ...$extra]);
        if (!array_key_exists('reason', $extra) || $extra['reason'] !== '') {
            $response->assertSessionHasNoErrors();
        }

        return $response;
    }

    private function save(Letter $letter, array $extra = []): TestResponse
    {
        $draft = $letter->draft;

        return $this->put(route('ltr.update', $letter->uuid), ['template_version' => $draft->templateVersion->uuid, 'draft_id' => $draft->uuid, 'base_version' => $draft->baseVersion?->uuid, 'revision' => $draft->revision, 'subject' => $draft->subject ?? $letter->subject, 'reason' => $draft->reason, 'letter_date' => $draft->letter_date->toDateString(), 'numbering_mode' => $draft->numbering_mode->value, 'number_year' => $draft->number_year, 'number_month' => $draft->number_month, 'page_number' => $draft->page_number, 'sequence_number' => $draft->sequence_number, 'values' => $draft->form_data['values'], 'signers' => $draft->form_data['signers'], ...$extra]);
    }

    private function publish(Letter $letter, bool $expectReasonFailure = false, bool $expectNumberFailure = false): void
    {
        Process::fake(['*letters:render-preview*' => fn($process) => Process::result(exitCode: Artisan::call('letters:render-preview', ['directory' => $process->command[3]]))]);
        $draft = $letter->draft;
        $uuid = $this->postJson(route('ltr.previews.store', $letter->uuid), ['draft_id' => $draft->uuid, 'base_version' => $draft->baseVersion?->uuid, 'revision' => $draft->revision, 'idempotency_key' => (string) Str::uuid()])->assertAccepted()->json('operation.id');
        app(RunLetterPreview::class)->run($uuid);
        $artifact = LetterArtifact::query()->where('letter_draft_id', $draft->id)->where('kind', 'docx')->latest('id')->firstOrFail();
        $artifact = $this->pdfArtifact($artifact);
        $check = ['draft_id' => $draft->uuid, 'base_version' => $draft->baseVersion?->uuid, 'revision' => $draft->revision, 'fingerprint' => app(LetterPdfState::class)->evidence($artifact), 'preview_kind' => 'pdf', 'confirmed' => true];
        $this->postJson(route('ltr.previews.rendered', [$letter->uuid, $artifact->uuid]), $check)->assertOk();
        $this->postJson(route('ltr.previews.confirm', [$letter->uuid, $artifact->uuid]), $check)->assertOk();
        $response = $this->postJson(route('ltr.issue', $letter->uuid), ['draft_id' => $draft->uuid, 'base_version' => $draft->baseVersion?->uuid, 'revision' => $draft->revision, 'artifact' => $artifact->uuid, 'fingerprint' => $check['fingerprint'], 'publication_month' => '2026-10', 'keep_period' => true, 'confirmed' => true, 'idempotency_key' => (string) Str::uuid()]);
        if ($expectReasonFailure || $expectNumberFailure) {
            $response->assertUnprocessable()->assertJsonValidationErrors($expectReasonFailure ? 'reason' : 'number');

            return;
        }
        $uuid = $response->assertAccepted()->json('operation.id');
        app(RunLetterIssuance::class)->run($uuid);
        $this->assertSame(OperationStatus::SUCCEEDED, LetterOperation::query()->where('uuid', $uuid)->sole()->status);
    }
}
